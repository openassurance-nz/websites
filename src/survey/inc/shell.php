<?php
/**
 * Page shell for the survey. build.py inlines the shared stylesheet here, as it
 * does for the static pages, because the site's content security policy allows
 * inline styles only and forbids every external resource.
 */

function oa_page_open(string $title): void
{
    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html>
<html lang="en-NZ">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — OpenAssurance</title>
<meta name="robots" content="noindex">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%230f766e'/%3E%3Cpath d='M8 16h16M17 10l7 6-7 6' stroke='white' stroke-width='2.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
<style>
{{SITE_CSS}}
</style>
<style>
{{SITE_ACCENT}}
  /* --- survey ----------------------------------------------------------- */
  .survey h1 { font-size: 1.9rem; margin: 0 0 .6rem; }
  .survey fieldset { border: 1px solid var(--rule); border-radius: 8px; padding: 1.1rem 1.25rem 1.25rem; margin: 0 0 1.4rem; }
  .survey legend { font-family: ui-sans-serif, system-ui, sans-serif; font-weight: 620; padding: 0 .4rem; }
  .survey .q { margin: 1.15rem 0 0; }
  .survey .q > p { margin: 0 0 .45rem; font-weight: 560; }
  .survey .q small, .survey .hint { display: block; color: var(--ink-soft); font-size: .9rem; font-weight: 400; }
  .survey label.opt { display: block; padding: .18rem 0 .18rem 1.7rem; text-indent: -1.7rem; }
  .survey label.opt input { margin-right: .55rem; }
  .survey textarea, .survey input[type=text] {
    width: 100%; box-sizing: border-box; font: inherit; color: inherit;
    background: var(--bg); border: 1px solid var(--rule); border-radius: 6px; padding: .55rem .65rem;
  }
  .survey textarea { min-height: 7.5rem; }
  .survey button { font: inherit; cursor: pointer; }
  .survey .notice { border-left: 3px solid var(--accent); background: var(--bg-alt); padding: .85rem 1.1rem; margin: 1.2rem 0; }
  .survey .errors { border-left: 3px solid #b42318; background: var(--bg-alt); padding: .85rem 1.1rem; margin: 1.2rem 0; }
  .survey .code { font-family: ui-monospace, monospace; font-size: 1.35rem; letter-spacing: .08em; }
  .survey .elsewhere { position: absolute; left: -6000px; width: 1px; height: 1px; overflow: hidden; }
  .survey table { border-collapse: collapse; width: 100%; font-size: .9rem; margin: .6rem 0 1.6rem; }
  .survey th, .survey td { text-align: left; vertical-align: top; border-bottom: 1px solid var(--rule); padding: .4rem .6rem .4rem 0; }
  .survey td.n { text-align: right; white-space: nowrap; }
</style>
</head>
<body>

<a class="skip" href="#main">Skip to content</a>

<header class="top">
  <div class="wrap">
    <a class="brand" href="/">
      <svg width="22" height="22" viewBox="0 0 32 32" aria-hidden="true">
        <rect width="32" height="32" rx="6" fill="currentColor" opacity=".1"/>
        <path d="M8 16h16M17 10l7 6-7 6" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      OpenAssurance
    </a>
    <nav class="top-nav" aria-label="Sections">
      <a href="/">Home</a>
      <a href="/survey/">Survey</a>
      <a href="/survey/remove.php">Remove a response</a>
    </nav>
  </div>
</header>

<main id="main" class="survey">
<?php
}

function oa_page_close(): void
{
    ?>
</main>

<footer>
  <div class="wrap">
    <p class="fineprint">
      This survey sets no cookies, uses no analytics or third-party scripts, and does not store your
      IP address with your response. To discuss the project in the open, raise an issue at
      <a href="https://github.com/openassurance-nz/openassurance/issues">github.com/openassurance-nz</a>.
    </p>
  </div>
</footer>

</body>
</html>
<?php
}
