<?php
require __DIR__ . '/../inc/lib.php';
oa_no_store();
$user = oa_require_admin();

$db = oa_db();
$message = '';

if ($db !== null && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $ok = oa_token_ok($_POST['t'] ?? null, $_POST['k'] ?? null, 'admin', 0);
    $id = $_POST['delete'] ?? '';
    if ($ok && is_string($id) && ctype_digit($id) && !empty($_POST['confirm'])) {
        $stmt = $db->prepare('DELETE FROM survey_responses WHERE id = :id');
        $stmt->execute([':id' => (int) $id]);
        $message = 'Response ' . (int) $id . ' deleted.';
    } else {
        $message = 'Nothing was deleted. Tick the confirm box beside the response and try again.';
    }
}

$rows = [];
if ($db !== null) {
    try {
        $rows = $db->query('SELECT id, created_at, survey_version, role, answers, comment, contact FROM survey_responses ORDER BY id DESC')->fetchAll();
    } catch (Throwable $e) {
        $rows = [];   // the setup check on this page explains what is missing
    }
}

// Totals per question and option, counted here so the question set can change without a schema change.
$questions = survey_questions();
$totals = [];
foreach ($rows as $row) {
    $answers = json_decode((string) $row['answers'], true) ?: [];
    foreach ($answers as $id => $value) {
        foreach ((array) $value as $code) {
            $totals[$id][$code] = ($totals[$id][$code] ?? 0) + 1;
        }
    }
}

oa_page_open('Survey results');
[$t, $k] = oa_token('admin');
?>
<section>
  <div class="wrap">
    <h1>Survey results</h1>
    <p class="hint">Signed in as <?= h($user) ?>. This page is not indexed and is never cached.</p>
    <p><strong>Survey results</strong> · <a href="/survey/admin/messages.php">Messages</a></p>
<?php
$checks = oa_diagnose();
$ready = !array_filter($checks, function ($c) { return !$c[1]; });
if (!$ready || isset($_GET['check'])):
?>
    <h2>Setup check</h2>
    <table>
      <thead><tr><th>Step</th><th>Result</th><th>Detail</th></tr></thead>
      <tbody>
<?php foreach ($checks as $check): ?>
        <tr><td><?= h($check[0]) ?></td><td><strong><?= $check[1] ? 'OK' : 'Not yet' ?></strong></td><td><?= h($check[2]) ?></td></tr>
<?php endforeach; ?>
      </tbody>
    </table>
    <p class="hint">The check stops at the first step that fails. Fix it, then reload this page.</p>
<?php endif; ?>
<?php if ($db === null || !$ready): ?>
    <div class="errors"><p>The survey is closed to visitors until every step above says OK.</p></div>
<?php else: ?>
<?php if ($message !== ''): ?>
    <div class="notice"><p><?= h($message) ?></p></div>
<?php endif; ?>
    <p><strong><?= count($rows) ?></strong> responses. <a href="/survey/admin/export.php">Download as CSV</a>. <a href="/survey/admin/?check=1">Setup check</a>.</p>
    <div class="notice">
      <p>
        Before publishing any total, leave out every answer chosen by fewer than five organisations,
        marked <strong>*</strong> below, and never publish a comment or a contact detail.
      </p>
    </div>

    <h2>Totals</h2>
<?php foreach ($questions as $id => $q): ?>
<?php if ($q['type'] === 'text') { continue; } ?>
    <table>
      <thead><tr><th><?= h($q['label']) ?></th><th class="n">Count</th></tr></thead>
      <tbody>
<?php foreach ($q['options'] as $code => $label): ?>
<?php $n = $totals[$id][$code] ?? 0; ?>
        <tr><td><?= h($label) ?></td><td class="n"><?= $n ?><?= ($n > 0 && $n < 5) ? ' *' : '' ?></td></tr>
<?php endforeach; ?>
      </tbody>
    </table>
<?php endforeach; ?>

    <h2>Responses</h2>
    <form method="post" action="/survey/admin/">
      <input type="hidden" name="t" value="<?= h($t) ?>">
      <input type="hidden" name="k" value="<?= h($k) ?>">
      <table>
        <thead><tr><th>#</th><th>Received (UTC)</th><th>Answers</th><th>Comment</th><th>Contact</th><th>Delete</th></tr></thead>
        <tbody>
<?php foreach ($rows as $row): ?>
<?php $answers = json_decode((string) $row['answers'], true) ?: []; ?>
          <tr>
            <td><?= (int) $row['id'] ?></td>
            <td><?= h(substr((string) $row['created_at'], 0, 16)) ?><br><span class="hint"><?= h($row['survey_version']) ?></span></td>
            <td>
<?php foreach ($answers as $id => $value): ?>
              <span class="hint"><?= h($id) ?>:</span> <?= h(oa_answer_label((string) $id, $value)) ?><br>
<?php endforeach; ?>
            </td>
            <td><?= nl2br(h($row['comment'] ?? '')) ?></td>
            <td><?= h($row['contact'] ?? '') ?></td>
            <td><button class="btn" type="submit" name="delete" value="<?= (int) $row['id'] ?>">Delete</button></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
      <p><label class="opt"><input type="checkbox" name="confirm" value="1"> Yes, delete the response whose button I press</label></p>
    </form>
<?php endif; ?>
  </div>
</section>
<?php
oa_page_close();
