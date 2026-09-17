<?php
require __DIR__ . '/../survey/inc/lib.php';
oa_no_store();

// The form shares the survey's library. If the two are out of step, because
// only part of a build was uploaded, say so politely and do not fail.
if (!function_exists('oa_contact_open')) {
    http_response_code(503);
    oa_page_open('Contact');
    echo '<section><div class="wrap"><h1>Get in touch</h1><div class="notice"><p>This form is not open yet. ';
    echo 'Please come back soon, or raise an issue on GitHub if you would like to talk now.</p></div>';
    echo '<p><a class="btn" href="https://github.com/openassurance-nz/openassurance/issues">Open a discussion</a></p></div></section>';
    oa_page_close();
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: /contact/', true, 303);
    exit;
}

function oa_fail(string $heading, array $messages, int $status = 400): void
{
    http_response_code($status);
    oa_page_open($heading);
    echo '<section><div class="wrap"><h1>' . h($heading) . '</h1><div class="errors">';
    foreach ($messages as $m) {
        echo '<p>' . h($m) . '</p>';
    }
    echo '</div><p>Your browser\'s back button will return you to what you wrote.</p>';
    echo '<p><a class="btn" href="/contact/">Back to the form</a></p></div></section>';
    oa_page_close();
    exit;
}

if (!oa_contact_open()) {
    oa_fail('The form is not open', ['Nothing was saved. Please try again later.'], 503);
}
$db = oa_db();

// A field people cannot see and only automated submissions fill in. Answer as
// if it worked, so that whatever sent it learns nothing.
if (trim((string) ($_POST['oa_leave_empty'] ?? '')) !== '') {
    oa_page_open('Thank you');
    echo '<section><div class="wrap"><h1>Thank you</h1><p>Your message has been received.</p></div></section>';
    oa_page_close();
    exit;
}

if (!oa_token_ok($_POST['t'] ?? null, $_POST['k'] ?? null, 'contact')) {
    oa_fail('Please try again', ['The form was open for too long, or was sent too quickly to have been filled in by a person. Nothing was saved.']);
}

// Only values from the lists are kept, so nothing unexpected can reach the database.
$topic = (string) ($_POST['topic'] ?? 'general');
if (!array_key_exists($topic, oa_contact_topics())) {
    $topic = 'general';
}
$role = (string) ($_POST['role'] ?? '');
if (!array_key_exists($role, oa_contact_roles())) {
    $role = '';
}

$message = oa_clean_text($_POST['message'] ?? '', OA_MESSAGE_MAX);
$errors = [];
$length = function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message);
if ($length < OA_MESSAGE_MIN) {
    $errors[] = 'Please write a message.';
}
if (oa_link_count($message) > OA_MESSAGE_MAX_LINKS) {
    $errors[] = 'Please include no more than ' . OA_MESSAGE_MAX_LINKS . ' links. This keeps automated advertising out.';
}

$name = $organisation = $email = '';
$mayContact = 0;
if (oa_contact_enabled()) {
    $name = oa_clean_line($_POST['name'] ?? '', 120);
    $organisation = oa_clean_line($_POST['organisation'] ?? '', 160);
    $email = oa_clean_line($_POST['email'] ?? '', 200);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'That email address does not look right. Correct it, or leave it blank.';
    }
    $mayContact = (!empty($_POST['may_contact']) && $email !== '') ? 1 : 0;
}
if ($errors) {
    oa_fail('One thing needs attention', $errors);
}

try {
    if (oa_contact_throttled($db)) {
        oa_fail('Please try again later', ['The form is receiving more messages than usual. Nothing was saved.'], 429);
    }
    $code = oa_new_removal_code();
    $stmt = $db->prepare(
        'INSERT INTO contact_messages (created_at, topic, role, message, name, organisation, email, may_contact, removal_hash)
         VALUES (:created_at, :topic, :role, :message, :name, :organisation, :email, :may_contact, :removal_hash)'
    );
    $stmt->execute([
        ':created_at' => gmdate('Y-m-d H:i:s'),
        ':topic' => $topic,
        ':role' => $role !== '' ? $role : null,
        ':message' => $message,
        ':name' => $name !== '' ? $name : null,
        ':organisation' => $organisation !== '' ? $organisation : null,
        ':email' => $email !== '' ? $email : null,
        ':may_contact' => $mayContact,
        ':removal_hash' => oa_removal_hash($code),
    ]);
    $id = (int) $db->lastInsertId();
} catch (Throwable $e) {
    error_log('OpenAssurance contact: could not save a message');
    oa_fail('Something went wrong', ['Your message could not be saved. Please try again later.'], 500);
}

oa_purge_old_messages($db);

// The notification says that a message has arrived, and nothing about what it
// says or who sent it. It is read in the admin area, over HTTPS.
oa_notify(
    'OpenAssurance: a new message has arrived',
    "A new message has arrived through the contact form on openassurance.nz.\n\n"
    . 'Message number: ' . $id . "\n"
    . 'About: ' . oa_contact_topics()[$topic] . "\n"
    . 'Reply requested: ' . ($mayContact ? 'yes' : 'no') . "\n\n"
    . "Read it in the admin area:\nhttps://openassurance.nz/survey/admin/messages.php\n\n"
    . "This notification carries no part of the message, because email is not private.\n"
);

oa_page_open('Thank you');
?>
<section>
  <div class="wrap">
    <h1>Thank you</h1>
    <p class="lede">Your message has been saved.</p>
    <div class="notice">
      <p><strong>Your removal code</strong></p>
      <p class="code"><?= h($code) ?></p>
      <p>
        Keep this if you may want to delete your message later. It is shown once, it is not stored
        in a readable form, and it cannot be recovered.
      </p>
    </div>
<?php if ($mayContact): ?>
    <p>You asked for a reply, and one will come from a person at the project, by email.</p>
<?php else: ?>
    <p>You did not ask for a reply, so none will be sent.</p>
<?php endif; ?>
    <div class="cta">
      <a class="btn btn-primary" href="/">Back to OpenAssurance</a>
      <a class="btn" href="/survey/remove.php">Remove a message</a>
    </div>
  </div>
</section>
<?php
oa_page_close();
