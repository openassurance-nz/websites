<?php
require __DIR__ . '/inc/lib.php';
oa_no_store();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: /survey/', true, 303);
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
    echo '</div><p><a class="btn" href="/survey/">Back to the survey</a></p></div></section>';
    oa_page_close();
    exit;
}

$db = oa_db();
if ($db === null) {
    oa_fail('The survey is not open', ['Nothing was saved. Please try again later.'], 503);
}

// A field people cannot see and only automated submissions fill in. Answer as
// if it worked, so that whatever sent it learns nothing.
if (trim((string) ($_POST['oa_leave_empty'] ?? '')) !== '') {
    oa_page_open('Thank you');
    echo '<section><div class="wrap"><h1>Thank you</h1><p>Your answers have been received.</p></div></section>';
    oa_page_close();
    exit;
}

if (!oa_token_ok($_POST['t'] ?? null, $_POST['k'] ?? null)) {
    oa_fail('Please try again', ['The form was open for too long, or was sent too quickly to have been filled in by a person. Nothing was saved.']);
}

[$answers, $comment, $errors] = oa_validate($_POST);
if ($errors) {
    oa_fail('One thing is missing', $errors);
}

try {
    if (oa_throttled($db)) {
        oa_fail('Please try again later', ['The survey is receiving more responses than usual. Nothing was saved.'], 429);
    }
    $contact = oa_contact_enabled() ? oa_clean_text($_POST['contact'] ?? '', OA_CONTACT_MAX) : '';
    $code = oa_new_removal_code();
    $stmt = $db->prepare(
        'INSERT INTO survey_responses (created_at, survey_version, role, answers, comment, contact, removal_hash)
         VALUES (:created_at, :version, :role, :answers, :comment, :contact, :removal_hash)'
    );
    $stmt->execute([
        ':created_at' => gmdate('Y-m-d H:i:s'),
        ':version' => SURVEY_VERSION,
        ':role' => $answers['role'],
        ':answers' => json_encode($answers, JSON_UNESCAPED_UNICODE),
        ':comment' => $comment !== '' ? $comment : null,
        ':contact' => $contact !== '' ? $contact : null,
        ':removal_hash' => oa_removal_hash($code),
    ]);
} catch (Throwable $e) {
    error_log('OpenAssurance survey: could not save a response');
    oa_fail('Something went wrong', ['Your answers could not be saved. Please try again later.'], 500);
}

oa_page_open('Thank you');
?>
<section>
  <div class="wrap">
    <h1>Thank you</h1>
    <p class="lede">Your answers have been saved.</p>
    <div class="notice">
      <p><strong>Your removal code</strong></p>
      <p class="code"><?= h($code) ?></p>
      <p>
        Keep this if you may want to delete your response later. It is shown once, it is not stored
        in a readable form, and it cannot be recovered.
      </p>
    </div>
    <p>
      Totals will be published in the project repository once enough organisations have answered.
      If you would like to discuss the proposal, or disagree with it, the most useful place is an
      issue on GitHub.
    </p>
    <div class="cta">
      <a class="btn btn-primary" href="https://github.com/openassurance-nz/openassurance/issues">Open a discussion</a>
      <a class="btn" href="/">Back to OpenAssurance</a>
    </div>
  </div>
</section>
<?php
oa_page_close();
