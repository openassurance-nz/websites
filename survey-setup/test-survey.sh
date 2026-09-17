#!/usr/bin/env bash
# End-to-end test of the survey against SQLite, using PHP's built-in server.
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
rm -f "$DB"

cat > "$S/survey-test-config.php" <<EOF
<?php
return [
  'dsn' => 'sqlite:$DB',
  'secret' => 'local-test-secret',
  'collect_contact' => true,
  'holder_notice' => 'Test notice: held by the test operator for follow-up only.',
  'max_per_hour' => 50,
];
EOF

"${PHPX[@]}" -r "
\$db = new PDO('sqlite:$DB');
\$db->exec('CREATE TABLE survey_responses (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL, survey_version TEXT NOT NULL, role TEXT NOT NULL, answers TEXT NOT NULL, comment TEXT NULL, contact TEXT NULL, removal_hash TEXT NOT NULL)');
echo 'table created', PHP_EOL;"

pass=0; fail=0
check() { if [ "$2" = "$3" ]; then pass=$((pass+1)); echo "  ok    $1"; else fail=$((fail+1)); echo "  FAIL  $1 (expected '$3', got '$2')"; fi; }
has()   { if echo "$2" | grep -q -- "$3"; then pass=$((pass+1)); echo "  ok    $1"; else fail=$((fail+1)); echo "  FAIL  $1 (missing '$3')"; fi; }
hasnt() { if echo "$2" | grep -q -- "$3"; then fail=$((fail+1)); echo "  FAIL  $1 (found '$3')"; else pass=$((pass+1)); echo "  ok    $1"; fi; }
rows()  { "${PHPX[@]}" -r "\$db=new PDO('sqlite:$DB'); echo \$db->query('SELECT COUNT(*) FROM survey_responses')->fetchColumn();"; }

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
T=$(echo "$form" | grep -o 'name="t" value="[0-9]*"' | grep -o '[0-9]*' | head -1)
K=$(echo "$form" | grep -o 'name="k" value="[a-f0-9]*"' | sed 's/.*value="//;s/"//' | head -1)

fast=$(curl -s -o /dev/null -w "%{http_code}" -d "role=supplier&t=$T&k=$K" $B/survey/submit.php)
check "too-fast submission rejected"      "$fast" "400"
check "nothing saved yet"                 "$(rows)" "0"

sleep 5
bad=$(curl -s -o /dev/null -w "%{http_code}" -d "role=supplier&t=$T&k=deadbeef" $B/survey/submit.php)
check "forged token rejected"             "$bad" "400"
miss=$(curl -s -o /dev/null -w "%{http_code}" -d "sector=energy&t=$T&k=$K" $B/survey/submit.php)
check "missing role rejected"             "$miss" "400"
pot=$(curl -s -d "role=supplier&website=http://spam.example&t=$T&k=$K" $B/survey/submit.php)
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

adm=$(curl -s -o /dev/null -w "%{http_code}" $B/survey/admin/index.php)
check "admin refused without server auth" "$adm" "403"
adm2=$(curl -s -o /dev/null -w "%{http_code}" -u anyone:anything $B/survey/admin/index.php)
check "admin refused with a made-up Authorization header" "$adm2" "403"
exp=$(curl -s -o /dev/null -w "%{http_code}" -u anyone:anything $B/survey/admin/export.php)
check "export refused the same way"       "$exp" "403"
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
csv=$(curl -s $B/survey/admin/export.php)
has   "csv has a header row"              "$csv" "received_utc"
has   "csv formula is defused"            "$csv" "'=cmd"
check "table survived the injection text" "$(rows)" "1"

rform=$(curl -s $B/survey/remove.php)
RT=$(echo "$rform" | grep -o 'name="t" value="[0-9]*"' | grep -o '[0-9]*' | head -1)
RK=$(echo "$rform" | grep -o 'name="k" value="[a-f0-9]*"' | sed 's/.*value="//;s/"//' | head -1)
sleep 3
wrong=$(curl -s --data-urlencode "code=AAAA-BBBB-CCCC" --data-urlencode "t=$RT" --data-urlencode "k=$RK" $B/survey/remove.php)
has   "wrong code gets the same answer"   "$wrong" "If a response matched"
check "wrong code deleted nothing"        "$(rows)" "1"
right=$(curl -s --data-urlencode "code=$CODE" --data-urlencode "t=$RT" --data-urlencode "k=$RK" $B/survey/remove.php)
has   "right code gets the same answer"   "$right" "If a response matched"
check "right code deleted the response"   "$(rows)" "0"
kill $PID 2>/dev/null; wait $PID 2>/dev/null

echo "== contact field hidden until a holder is named =="
sed -i "s/'holder_notice' => .*/'holder_notice' => 'REPLACE me',/" "$S/survey-test-config.php"
OA_SURVEY_CONFIG="$S/survey-test-config.php" "${PHPX[@]}" -S 127.0.0.1:8767 -t "$SITE" >/dev/null 2>&1 &
PID=$!; sleep 1.5
form=$(curl -s http://127.0.0.1:8767/survey/index.php)
hasnt "no contact field without a holder notice" "$form" 'name="contact"'
kill $PID 2>/dev/null; wait $PID 2>/dev/null

echo "== no configuration at all =="
OA_SURVEY_CONFIG="$S/does-not-exist.php" "${PHPX[@]}" -S 127.0.0.1:8768 -t "$SITE" >/dev/null 2>&1 &
PID=$!; sleep 1.5
form=$(curl -s http://127.0.0.1:8768/survey/index.php)
has   "closed politely with no config"    "$form" "not open yet"
hasnt "no error text leaked"              "$form" "Warning"
kill $PID 2>/dev/null; wait $PID 2>/dev/null

rm -f "$DB" "$S/survey-test-config.php"
echo; echo "passed: $pass   failed: $fail"
