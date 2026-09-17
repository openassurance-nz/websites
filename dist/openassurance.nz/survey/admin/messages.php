<?php
require __DIR__ . '/../inc/lib.php';
oa_no_store();
$user = oa_require_admin();

$db = oa_db();
$open = oa_contact_open();
$message = '';

if ($open && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $ok = oa_token_ok($_POST['t'] ?? null, $_POST['k'] ?? null, 'admin-messages', 0);
    $id = $_POST['delete'] ?? '';
    if ($ok && is_string($id) && ctype_digit($id) && !empty($_POST['confirm'])) {
        $stmt = $db->prepare('DELETE FROM contact_messages WHERE id = :id');
        $stmt->execute([':id' => (int) $id]);
        $message = 'Message ' . (int) $id . ' deleted.';
    } else {
        $message = 'Nothing was deleted. Tick the confirm box and try again.';
    }
}

$rows = [];
if ($open) {
    oa_purge_old_messages($db);
    $rows = $db->query('SELECT id, created_at, topic, role, message, name, organisation, email, may_contact FROM contact_messages ORDER BY id DESC')->fetchAll();
}

$c = oa_config();
$notify = trim((string) ($c['notify_email'] ?? ''));
$notifyOn = $notify !== '' && stripos($notify, 'REPLACE') === false && filter_var($notify, FILTER_VALIDATE_EMAIL);

oa_page_open('Messages');
[$t, $k] = oa_token('admin-messages');
$topics = oa_contact_topics();
$roles = oa_contact_roles();
?>
<section>
  <div class="wrap">
    <h1>Messages</h1>
    <p class="hint">Signed in as <?= h($user) ?>. This page is not indexed and is never cached.</p>
    <p><a href="/survey/admin/">Survey results</a> · <strong>Messages</strong></p>

<?php if (!$open): ?>
    <div class="errors">
      <p>
        The contact form is closed to visitors until the table <code>contact_messages</code> exists.
        Run <code>survey-setup/schema.sql</code> again in phpMyAdmin: it only adds what is missing.
        If the survey results page also reports a problem, fix that first.
      </p>
    </div>
<?php else: ?>
<?php if ($message !== ''): ?>
    <div class="notice"><p><?= h($message) ?></p></div>
<?php endif; ?>
    <p>
      <strong><?= count($rows) ?></strong> messages.
      <a href="/survey/admin/messages-export.php">Download as CSV</a>.
      Messages are deleted automatically after <?= (int) oa_retention_days() ?> days.
    </p>
    <div class="notice">
      <p>
        Notification email is <strong><?= $notifyOn ? 'on' : 'off' ?></strong>.
        <?= $notifyOn
            ? 'A notice is sent to the address in the configuration file when a message arrives. It carries no part of the message.'
            : 'To turn it on, set notify_email in the configuration file on the server.' ?>
      </p>
      <p>Reply from your own mail. Only reply where the sender ticked the box, and never publish a message or a sender's details.</p>
    </div>

    <form method="post" action="/survey/admin/messages.php">
      <input type="hidden" name="t" value="<?= h($t) ?>">
      <input type="hidden" name="k" value="<?= h($k) ?>">
      <table>
        <thead><tr><th>#</th><th>Received (UTC)</th><th>About</th><th>Message</th><th>From</th><th>Delete</th></tr></thead>
        <tbody>
<?php foreach ($rows as $row): ?>
          <tr>
            <td><?= (int) $row['id'] ?></td>
            <td><?= h(substr((string) $row['created_at'], 0, 16)) ?></td>
            <td><?= h($topics[$row['topic']] ?? $row['topic']) ?><br><span class="hint"><?= h($roles[$row['role'] ?? ''] ?? '') ?></span></td>
            <td><?= nl2br(h($row['message'])) ?></td>
            <td>
              <?= h($row['name'] ?? '') ?><br>
              <?= h($row['organisation'] ?? '') ?><br>
<?php if (!empty($row['email'])): ?>
              <a href="mailto:<?= h($row['email']) ?>"><?= h($row['email']) ?></a><br>
<?php endif; ?>
              <span class="hint"><?= !empty($row['may_contact']) ? 'Reply requested' : 'No reply requested' ?></span>
            </td>
            <td><button class="btn" type="submit" name="delete" value="<?= (int) $row['id'] ?>">Delete</button></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
      <p><label class="opt"><input type="checkbox" name="confirm" value="1"> Yes, delete the message whose button I press</label></p>
    </form>
<?php endif; ?>
  </div>
</section>
<?php
oa_page_close();
