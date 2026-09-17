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

$open = oa_contact_open();
$topics = oa_contact_topics();
$topic = (string) ($_GET['topic'] ?? 'general');
if (!array_key_exists($topic, $topics)) {
    $topic = 'general';
}
$details = oa_contact_enabled();

oa_page_open('Contact');
?>
<section>
  <div class="wrap">
    <h1>Get in touch</h1>
    <p class="lede">
      Questions, disagreement, and reasons this will not work are all useful. If you would rather
      talk in the open, an issue on GitHub reaches everyone following the project.
    </p>

<?php if (!$open): ?>
    <div class="notice">
      <p>This form is not open yet. Please come back soon, or raise an issue on GitHub if you would like to talk now.</p>
    </div>
    <p><a class="btn" href="https://github.com/openassurance-nz/openassurance/issues">Open a discussion</a></p>
<?php else: ?>
    <div class="notice">
      <p><strong>What happens to your message.</strong></p>
      <p>
        No cookies are set, and your IP address is not stored with your message. Messages are held
        in a database on this site's own host, are read only by the project, are not given to anyone
        else, and are deleted after <?= (int) round(oa_retention_days() / 30) ?> months.
        When you send one, you will be shown a removal code that lets you delete it sooner.
        The web host keeps ordinary access logs for every page on the site, and they are not linked
        to messages.
      </p>
<?php if ($details): ?>
      <p><?= h(oa_contact_notice()) ?></p>
<?php endif; ?>
    </div>

    <form method="post" action="/contact/submit.php" novalidate>
<?php [$t, $k] = oa_token('contact'); ?>
      <fieldset>
        <legend>Your message</legend>
        <div class="q">
          <p id="q-topic">What is it about?</p>
          <select name="topic" aria-labelledby="q-topic">
<?php foreach ($topics as $code => $label): ?>
            <option value="<?= h($code) ?>"<?= $code === $topic ? ' selected' : '' ?>><?= h($label) ?></option>
<?php endforeach; ?>
          </select>
        </div>
        <div class="q">
          <p id="q-message">Message</p>
          <textarea name="message" maxlength="<?= OA_MESSAGE_MAX ?>" aria-labelledby="q-message" required></textarea>
        </div>
        <div class="q">
          <p id="q-role">Which best describes your organisation? <small>Optional.</small></p>
          <select name="role" aria-labelledby="q-role">
            <option value="">Prefer not to say</option>
<?php foreach (oa_contact_roles() as $code => $label): ?>
            <option value="<?= h($code) ?>"><?= h($label) ?></option>
<?php endforeach; ?>
          </select>
        </div>
      </fieldset>

<?php if ($details): ?>
      <fieldset>
        <legend>If you would like a reply</legend>
        <p class="hint">Entirely optional. Leave these blank to stay anonymous.</p>
        <div class="q">
          <p id="q-name">Your name</p>
          <input type="text" name="name" maxlength="120" autocomplete="name" aria-labelledby="q-name">
        </div>
        <div class="q">
          <p id="q-organisation">Organisation</p>
          <input type="text" name="organisation" maxlength="160" autocomplete="organization" aria-labelledby="q-organisation">
        </div>
        <div class="q">
          <p id="q-email">Email address</p>
          <input type="email" name="email" maxlength="200" autocomplete="email" aria-labelledby="q-email">
        </div>
        <p><label class="opt"><input type="checkbox" name="may_contact" value="1"> You may contact me about this message</label></p>
      </fieldset>
<?php endif; ?>

      <p class="elsewhere" aria-hidden="true">
        <label>Leave this field empty <input type="text" name="oa_leave_empty" tabindex="-1" autocomplete="off"></label>
      </p>
      <input type="hidden" name="t" value="<?= h($t) ?>">
      <input type="hidden" name="k" value="<?= h($k) ?>">
      <p><button class="btn btn-primary" type="submit">Send my message</button></p>
    </form>
<?php endif; ?>
  </div>
</section>
<?php
oa_page_close();
