<?php
require __DIR__ . '/inc/lib.php';
oa_no_store();

$open = oa_db() !== null;
oa_page_open('Survey');

/** Render one question. $sticky holds a previous attempt's values after a validation error. */
function oa_render_question(string $id, array $q, array $sticky = []): void
{
    echo '<div class="q">';
    echo '<p id="q-' . h($id) . '">' . h($q['label']);
    if (!empty($q['help'])) {
        echo '<small>' . h($q['help']) . '</small>';
    }
    echo '</p>';
    if ($q['type'] === 'single' || $q['type'] === 'multi') {
        $multi = $q['type'] === 'multi';
        foreach ($q['options'] as $code => $label) {
            $name = $multi ? $id . '[]' : $id;
            $current = $sticky[$id] ?? null;
            $checked = $multi ? (is_array($current) && in_array((string) $code, $current, true)) : ((string) $current === (string) $code);
            printf(
                '<label class="opt"><input type="%s" name="%s" value="%s"%s%s> %s</label>',
                $multi ? 'checkbox' : 'radio',
                h($name),
                h($code),
                $checked ? ' checked' : '',
                (!$multi && !empty($q['required'])) ? ' required' : '',
                h($label)
            );
        }
    } elseif ($q['type'] === 'text') {
        printf(
            '<textarea name="%s" maxlength="%d" aria-labelledby="q-%s">%s</textarea>',
            h($id),
            (int) ($q['max'] ?? 1500),
            h($id),
            h($sticky[$id] ?? '')
        );
    }
    echo '</div>';
}
?>
<section>
  <div class="wrap">
    <h1>How much of this do you see?</h1>
    <p class="lede">
      OpenAssurance argues that workplace assurance information is recreated more often than it needs
      to be. This survey asks whether that matches your experience, and how much it costs you.
      It takes about three minutes.
    </p>

<?php if (!$open): ?>
    <div class="notice">
      <p>The survey is not open yet. Please come back soon, or raise an issue on GitHub if you would like to talk now.</p>
    </div>
<?php else: ?>
    <div class="notice">
      <p><strong>What happens to your answers.</strong></p>
      <p>
        The survey is anonymous unless you choose to leave contact details. No cookies are set, and
        your IP address is not stored with your response. Answers are held in a database on this
        site's own host and are not given to anyone else. Only totals will be published, and never
        where fewer than five organisations gave the same answer.
      </p>
      <p>
        Please do not name products, companies, or people. When you submit, you will be shown a
        removal code that lets you delete your response later. The web host keeps ordinary access
        logs for every page on the site, and they are not linked to responses.
      </p>
    </div>

    <form method="post" action="/survey/submit.php" novalidate>
<?php
    [$t, $k] = oa_token();
    foreach (survey_sections() as $section) {
        echo '<fieldset><legend>' . h($section['title']) . '</legend>';
        if ($section['intro'] !== '') {
            echo '<p class="hint">' . h($section['intro']) . '</p>';
        }
        foreach ($section['questions'] as $id => $q) {
            oa_render_question($id, $q);
        }
        echo '</fieldset>';
    }
    if (oa_contact_enabled()) {
        echo '<fieldset><legend>If you would like to talk</legend>';
        echo '<p class="hint">Entirely optional. Leave this blank to stay anonymous.</p>';
        echo '<div class="q"><p id="q-contact">An email address or phone number we may use to ask you more about your answers</p>';
        echo '<input type="text" name="contact" maxlength="' . OA_CONTACT_MAX . '" autocomplete="off" aria-labelledby="q-contact"></div>';
        echo '<p class="hint">' . h(oa_config()['holder_notice']) . '</p>';
        echo '</fieldset>';
    }
?>
      <p class="elsewhere" aria-hidden="true">
        <label>Leave this field empty <input type="text" name="oa_leave_empty" tabindex="-1" autocomplete="off"></label>
      </p>
      <input type="hidden" name="t" value="<?= h($t) ?>">
      <input type="hidden" name="k" value="<?= h($k) ?>">
      <p><button class="btn btn-primary" type="submit">Send my answers</button></p>
    </form>
<?php endif; ?>
  </div>
</section>
<?php
oa_page_close();
