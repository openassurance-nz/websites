<?php
require __DIR__ . '/inc/lib.php';
oa_no_store();

$done = false;
$problem = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $db = oa_db();
    if ($db === null) {
        $problem = 'Responses cannot be removed at the moment. Please try again later.';
    } elseif (!oa_token_ok($_POST['t'] ?? null, $_POST['k'] ?? null, 'remove', 2)) {
        $problem = 'Please try again.';
    } else {
        $code = (string) ($_POST['code'] ?? '');
        if (strlen(preg_replace('/[^A-Fa-f0-9]/', '', $code)) === 12) {
            try {
                $stmt = $db->prepare('DELETE FROM survey_responses WHERE removal_hash = :hash');
                $stmt->execute([':hash' => oa_removal_hash($code)]);
            } catch (Throwable $e) {
                error_log('OpenAssurance survey: could not remove a response');
            }
        }
        // The same answer whether or not a response matched, so that a code cannot be tested.
        $done = true;
    }
}

oa_page_open('Remove a response');
[$t, $k] = oa_token('remove');
?>
<section>
  <div class="wrap">
    <h1>Remove a response</h1>
<?php if ($done): ?>
    <div class="notice"><p>If a response matched that code, it has been deleted, including any contact details left with it.</p></div>
    <p><a class="btn" href="/">Back to OpenAssurance</a></p>
<?php else: ?>
    <p class="lede">Enter the removal code you were shown when you submitted the survey.</p>
<?php if ($problem !== ''): ?>
    <div class="errors"><p><?= h($problem) ?></p></div>
<?php endif; ?>
    <form method="post" action="/survey/remove.php">
      <div class="q">
        <p id="q-code">Removal code</p>
        <input type="text" name="code" maxlength="20" autocomplete="off" aria-labelledby="q-code" required>
      </div>
      <input type="hidden" name="t" value="<?= h($t) ?>">
      <input type="hidden" name="k" value="<?= h($k) ?>">
      <p style="margin-top:1.2rem"><button class="btn btn-primary" type="submit">Delete my response</button></p>
    </form>
<?php endif; ?>
  </div>
</section>
<?php
oa_page_close();
