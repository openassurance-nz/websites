#!/usr/bin/env bash
# End-to-end test of the survey and the contact form against SQLite, using
# PHP's built-in server.
# Run from anywhere after 'python build.py':  bash survey-setup/test-survey.sh
# Needs PHP with the pdo_sqlite extension available in its ext directory.
set -u
S="$(mktemp -d)"
# On Windows, PHP is a native program and needs a Windows-style path.
command -v cygpath >/dev/null 2>&1 && S="$(cygpath -m "$S")"
SITE="$(cd "$(dirname "$0")/.." && pwd)/dist/openassurance.nz"
EXT="$(php -r 'echo dirname(PHP_BINARY);')/ext"
PHPX=(php -d "extension_dir=$EXT" -d extension=pdo_sqlite -d extension=sqlite3)
DB="$S/survey-test.sqlite"
LOG="$S/notify.log"
rm -f "$DB" "$LOG"

cat > "$S/survey-test-config.php" <<EOF
<?php
return [
  'dsn' => 'sqlite:$DB',
  'secret' => 'local-test-secret',
  'collect_contact' => true,
  'holder_notice' => 'Test notice: held by the test operator for follow-up only.',
  'max_per_hour' => 50,
  'notify_email' => 'owner@example.org',
  'notify_log' => '$LOG',
  'message_retention_days' => 365,
];
EOF

"${PHPX[@]}" -r "
\$db = new PDO('sqlite:$DB');
\$db->exec('CREATE TABLE survey_responses (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL, survey_version TEXT NOT NULL, role TEXT NOT NULL, answers TEXT NOT NULL, comment TEXT NULL, contact TEXT NULL, removal_hash TEXT NOT NULL)');
\$db->exec('CREATE TABLE contact_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL, topic TEXT NOT NULL, role TEXT NULL, message TEXT NOT NULL, name TEXT NULL, organisation TEXT NULL, email TEXT NULL, may_contact INTEGER NOT NULL DEFAULT 0, removal_hash TEXT NOT NULL)');
echo 'tables created', PHP_EOL;"

pass=0; fail=0
check() { if [ "$2" = "$3" ]; then pass=$((pass+1)); echo "  ok    $1"; else fail=$((fail+1)); echo "  FAIL  $1 (expected '$3', got '$2')"; fi; }
has()   { if echo "$2" | grep -q -- "$3"; then pass=$((pass+1)); echo "  ok    $1"; else fail=$((fail+1)); echo "  FAIL  $1 (missing '$3')"; fi; }
hasnt() { if echo "$2" | grep -q -- "$3"; then fail=$((fail+1)); echo "  FAIL  $1 (found '$3')"; else pass=$((pass+1)); echo "  ok    $1"; fi; }
rows()  { "${PHPX[@]}" -r "\$db=new PDO('sqlite:$DB'); echo \$db->query('SELECT COUNT(*) FROM survey_responses')->fetchColumn();"; }
msgs()  { "${PHPX[@]}" -r "\$db=new PDO('sqlite:$DB'); echo \$db->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();"; }
token() { echo "$1" | grep -o "name=\"$2\" value=\"[a-f0-9]*\"" | sed 's/.*value="//;s/"//' | head -1; }

echo "== public pages, no admin =="
OA_SURVEY_CONFIG="$S/survey-test-config.php" "${PHPX[@]}" -S 127.0.0.1:8765 -t "$SITE" >/dev/null 2>&1 &
PID=$!; sleep 1.5
B=http://127.0.0.1:8765

form=$(curl -s $B/survey/index.php)
has   "form renders"                      "$form" "Send my answers"
has   "contact field shown when enabled"  "$form" 'name="contact"'
has   "holder notice shown"               "$form" "Test notice"
hasnt "no script tags"                    "$form" "<script"
hdr=$(curl -s -D - -o /dev/null $B/survey/index.php)
hasnt "no cookie set"                     "$hdr" "Set-Cookie"
has   "not cached"                        "$hdr" "no-store"
T=$(token "$form" t); K=$(token "$form" k)

cform=$(curl -s "$B/contact/index.php?topic=prequal")
has   "contact form renders"              "$cform" "Send my message"
has   "topic taken from the link"         "$cform" 'value="prequal" selected'
has   "reply fields shown when a holder is named" "$cform" 'name="email"'
has   "contact form shows the holder notice" "$cform" "Test notice"
has   "contact form states the retention" "$cform" "deleted after 12 months"
hasnt "contact form has no script tags"   "$cform" "<script"
hasnt "the owner's address is not on the page" "$cform" "owner@example.org"
chdr=$(curl -s -D - -o /dev/null $B/contact/index.php)
hasnt "contact form sets no cookie"       "$chdr" "Set-Cookie"
has   "contact form is not cached"        "$chdr" "no-store"
odd=$(curl -s "$B/contact/index.php?topic=%3Cscript%3E")
has   "an unknown topic falls back"       "$odd" 'value="general" selected'
CT=$(token "$cform" t); CK=$(token "$cform" k)

fast=$(curl -s -o /dev/null -w "%{http_code}" -d "role=supplier&t=$T&k=$K" $B/survey/submit.php)
check "too-fast submission rejected"      "$fast" "400"
check "nothing saved yet"                 "$(rows)" "0"
cfast=$(curl -s -o /dev/null -w "%{http_code}" --data-urlencode "message=This was sent far too quickly." -d "t=$CT&k=$CK" $B/contact/submit.php)
check "too-fast message rejected"         "$cfast" "400"

sleep 5
bad=$(curl -s -o /dev/null -w "%{http_code}" -d "role=supplier&t=$T&k=deadbeef" $B/survey/submit.php)
check "forged token rejected"             "$bad" "400"
miss=$(curl -s -o /dev/null -w "%{http_code}" -d "sector=energy&t=$T&k=$K" $B/survey/submit.php)
check "missing role rejected"             "$miss" "400"
pot=$(curl -s -d "role=supplier&oa_leave_empty=http://spam.example&t=$T&k=$K" $B/survey/submit.php)
has   "honeypot answered politely"        "$pot" "Thank you"
check "honeypot saved nothing"            "$(rows)" "0"

ok=$(curl -s --data-urlencode "role=supplier" --data-urlencode "sector=energy" --data-urlencode "size=20-99" \
  --data-urlencode "prequal_count=11-25" --data-urlencode "prequal_overlap=most" --data-urlencode "role_injected=x" \
  --data-urlencode "buyer_method[]=own" --data-urlencode "buyer_method[]=not-a-code" \
  --data-urlencode "priority=prequal" --data-urlencode "comment=<script>alert(1)</script> it's a lot; DROP TABLE survey_responses;--" \
  --data-urlencode "contact==cmd|' /C calc'!A0" --data-urlencode "t=$T" --data-urlencode "k=$K" $B/survey/submit.php)
has   "valid submission accepted"         "$ok" "Your removal code"
check "one row saved"                     "$(rows)" "1"
CODE=$(echo "$ok" | grep -o '[A-F0-9]\{4\}-[A-F0-9]\{4\}-[A-F0-9]\{4\}' | head -1)
has   "removal code has expected shape"   "$CODE" "-"
stored=$("${PHPX[@]}" -r "\$db=new PDO('sqlite:$DB'); \$r=\$db->query('SELECT answers, removal_hash FROM survey_responses')->fetch(PDO::FETCH_ASSOC); echo \$r['answers'],' ',strlen(\$r['removal_hash']);")
hasnt "unknown option code dropped"       "$stored" "not-a-code"
hasnt "unknown field dropped"             "$stored" "role_injected"
has   "removal code stored only as hash"  "$stored" " 64"

echo "== contact form =="
cbad=$(curl -s -o /dev/null -w "%{http_code}" --data-urlencode "message=A message with a forged token." -d "t=$CT&k=deadbeef" $B/contact/submit.php)
check "forged contact token rejected"     "$cbad" "400"
swap=$(curl -s -o /dev/null -w "%{http_code}" --data-urlencode "message=A message sent with the survey's token." -d "t=$T&k=$K" $B/contact/submit.php)
check "a survey token does not open the contact form" "$swap" "400"
empty=$(curl -s -o /dev/null -w "%{http_code}" --data-urlencode "message=hi" -d "t=$CT&k=$CK" $B/contact/submit.php)
check "a message that says nothing is rejected" "$empty" "400"
links=$(curl -s -o /dev/null -w "%{http_code}" --data-urlencode "message=Buy now http://a.example http://b.example www.c.example" -d "t=$CT&k=$CK" $B/contact/submit.php)
check "a message full of links is rejected" "$links" "400"
mail=$(curl -s -o /dev/null -w "%{http_code}" --data-urlencode "message=A real message with a bad address." --data-urlencode "email=not-an-address" -d "t=$CT&k=$CK" $B/contact/submit.php)
check "a bad email address is rejected"   "$mail" "400"
inj=$(curl -s -o /dev/null -w "%{http_code}" --data-urlencode "message=A real message with a header in the address." --data-urlencode $'email=a@b.example\r\nBcc: x@y.example' -d "t=$CT&k=$CK" $B/contact/submit.php)
check "a header smuggled into the address is rejected" "$inj" "400"
cpot=$(curl -s --data-urlencode "message=An automated message." -d "oa_leave_empty=http://spam.example&t=$CT&k=$CK" $B/contact/submit.php)
has   "contact honeypot answered politely" "$cpot" "Thank you"
check "nothing saved by any of those"     "$(msgs)" "0"

cok=$(curl -s --data-urlencode "topic=prequal" --data-urlencode "role=supplier" --data-urlencode "role_injected=x" \
  --data-urlencode "message=<script>alert(2)</script> We maintain industrial refrigeration; DROP TABLE contact_messages;--" \
  --data-urlencode $'name==HYPERLINK("http://x.example")\nSecond line' --data-urlencode "organisation=Ridgeline" \
  --data-urlencode "email=sender@example.net" --data-urlencode "may_contact=1" \
  --data-urlencode "t=$CT" --data-urlencode "k=$CK" $B/contact/submit.php)
has   "valid message accepted"            "$cok" "Your removal code"
has   "a reply is promised when asked for" "$cok" "You asked for a reply"
check "one message saved"                 "$(msgs)" "1"
MCODE=$(echo "$cok" | grep -o '[A-F0-9]\{4\}-[A-F0-9]\{4\}-[A-F0-9]\{4\}' | head -1)
mstored=$("${PHPX[@]}" -r "\$db=new PDO('sqlite:$DB'); \$r=\$db->query('SELECT topic, role, name, may_contact, removal_hash FROM contact_messages')->fetch(PDO::FETCH_ASSOC); echo \$r['topic'],'|',\$r['role'],'|',\$r['name'],'|',\$r['may_contact'],'|',strlen(\$r['removal_hash']);")
has   "topic and role stored as codes"    "$mstored" "prequal|supplier|"
hasnt "a name never keeps a line break"   "$mstored" "Second line$"
has   "name kept on one line"             "$mstored" "Second line|1|64"
note=$(cat "$LOG" 2>/dev/null)
has   "a notification was written"        "$note" "a new message has arrived"
has   "it goes to the configured address" "$note" "To: owner@example.org"
hasnt "it carries no part of the message" "$note" "refrigeration"
hasnt "it carries nothing about the sender" "$note" "sender@example.net"
hasnt "it does not name the sender's organisation" "$note" "Ridgeline"

adm=$(curl -s -o /dev/null -w "%{http_code}" $B/survey/admin/index.php)
check "admin refused without server auth" "$adm" "403"
adm2=$(curl -s -o /dev/null -w "%{http_code}" -u anyone:anything $B/survey/admin/index.php)
check "admin refused with a made-up Authorization header" "$adm2" "403"
exp=$(curl -s -o /dev/null -w "%{http_code}" -u anyone:anything $B/survey/admin/export.php)
check "export refused the same way"       "$exp" "403"
madm=$(curl -s -o /dev/null -w "%{http_code}" -u anyone:anything $B/survey/admin/messages.php)
check "messages refused the same way"     "$madm" "403"
mexp=$(curl -s -o /dev/null -w "%{http_code}" -u anyone:anything $B/survey/admin/messages-export.php)
check "messages export refused the same way" "$mexp" "403"
inc=$(curl -s $B/survey/inc/lib.php)
hasnt "the shared code prints nothing if fetched" "$inc" "notify_email"
kill $PID 2>/dev/null; wait $PID 2>/dev/null

echo "== admin area, with the local test hook =="
OA_SURVEY_TEST_ADMIN=1 OA_SURVEY_CONFIG="$S/survey-test-config.php" "${PHPX[@]}" -S 127.0.0.1:8766 -t "$SITE" >/dev/null 2>&1 &
PID=$!; sleep 1.5
B=http://127.0.0.1:8766
page=$(curl -s $B/survey/admin/index.php)
has   "admin shows the response"          "$page" "11 to 25"
has   "comment is escaped"                "$page" "&lt;script&gt;"
hasnt "comment is not executable"         "$page" "<script>alert"
has   "small counts are flagged"          "$page" "1 \*"
has   "admin links to the messages"       "$page" "/survey/admin/messages.php"
csv=$(curl -s $B/survey/admin/export.php)
has   "csv has a header row"              "$csv" "received_utc"
has   "csv formula is defused"            "$csv" "'=cmd"
check "table survived the injection text" "$(rows)" "1"

"${PHPX[@]}" -r "\$db=new PDO('sqlite:$DB'); \$db->exec(\"INSERT INTO contact_messages (created_at, topic, message, may_contact, removal_hash) VALUES ('2020-01-01 00:00:00', 'general', 'An old message that is past its retention period.', 0, 'x')\");"
check "an old message is in the table"    "$(msgs)" "2"
mpage=$(curl -s $B/survey/admin/messages.php)
has   "messages page shows the message"   "$mpage" "industrial refrigeration"
has   "message is escaped"                "$mpage" "&lt;script&gt;"
hasnt "message is not executable"         "$mpage" "<script>alert"
has   "sender's address is a link to reply" "$mpage" 'mailto:sender@example.net'
has   "notification is reported as on"    "$mpage" "Notification email is <strong>on"
hasnt "the owner's address is not shown even here" "$mpage" "owner@example.org"
hasnt "the old message is gone from the page" "$mpage" "past its retention"
check "the old message was deleted"       "$(msgs)" "1"
mcsv=$(curl -s $B/survey/admin/messages-export.php)
has   "messages csv has a header row"     "$mcsv" "reply_requested"
has   "messages csv formula is defused"   "$mcsv" "'=HYPERLINK"
check "messages table survived the injection text" "$(msgs)" "1"

rform=$(curl -s $B/survey/remove.php)
RT=$(token "$rform" t); RK=$(token "$rform" k)
sleep 3
wrong=$(curl -s --data-urlencode "code=AAAA-BBBB-CCCC" --data-urlencode "t=$RT" --data-urlencode "k=$RK" $B/survey/remove.php)
has   "wrong code gets the same answer"   "$wrong" "If a response or a message matched"
check "wrong code deleted no response"    "$(rows)" "1"
check "wrong code deleted no message"     "$(msgs)" "1"
right=$(curl -s --data-urlencode "code=$CODE" --data-urlencode "t=$RT" --data-urlencode "k=$RK" $B/survey/remove.php)
has   "right code gets the same answer"   "$right" "If a response or a message matched"
check "right code deleted the response"   "$(rows)" "0"
check "and left the message alone"        "$(msgs)" "1"
mright=$(curl -s --data-urlencode "code=$MCODE" --data-urlencode "t=$RT" --data-urlencode "k=$RK" $B/survey/remove.php)
has   "message code gets the same answer" "$mright" "If a response or a message matched"
check "message code deleted the message"  "$(msgs)" "0"
kill $PID 2>/dev/null; wait $PID 2>/dev/null

echo "== contact details hidden until a holder is named =="
sed -i "s/'holder_notice' => .*/'holder_notice' => 'REPLACE me',/" "$S/survey-test-config.php"
OA_SURVEY_CONFIG="$S/survey-test-config.php" "${PHPX[@]}" -S 127.0.0.1:8767 -t "$SITE" >/dev/null 2>&1 &
PID=$!; sleep 1.5
form=$(curl -s http://127.0.0.1:8767/survey/index.php)
hasnt "no contact field without a holder notice" "$form" 'name="contact"'
cform=$(curl -s http://127.0.0.1:8767/contact/index.php)
has   "the message box is still offered"  "$cform" 'name="message"'
hasnt "no email field without a holder notice" "$cform" 'name="email"'
hasnt "no name field without a holder notice" "$cform" 'name="name"'
kill $PID 2>/dev/null; wait $PID 2>/dev/null

echo "== no configuration at all =="
OA_SURVEY_CONFIG="$S/does-not-exist.php" "${PHPX[@]}" -S 127.0.0.1:8768 -t "$SITE" >/dev/null 2>&1 &
PID=$!; sleep 1.5
form=$(curl -s http://127.0.0.1:8768/survey/index.php)
has   "closed politely with no config"    "$form" "not open yet"
hasnt "no error text leaked"              "$form" "Warning"
cform=$(curl -s http://127.0.0.1:8768/contact/index.php)
has   "contact form closed politely too"  "$cform" "not open yet"
hasnt "no error text leaked there either" "$cform" "Warning"
csend=$(curl -s -o /dev/null -w "%{http_code}" --data-urlencode "message=Sent while the form is closed." http://127.0.0.1:8768/contact/submit.php)
check "sending while closed saves nothing" "$csend" "503"
kill $PID 2>/dev/null; wait $PID 2>/dev/null

rm -f "$DB" "$LOG" "$S/survey-test-config.php"
echo; echo "passed: $pass   failed: $fail"
