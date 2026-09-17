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
  /* Each site sets --accent / --accent-soft / --accent-ink for its own identity.
     Everything else is shared, so the three sites read as one family. */
  :root {
    color-scheme: light;
    --bg: #fbfaf8;
    --bg-alt: #f2f0ec;
    --surface: #ffffff;
    --ink: #16181c;
    --ink-soft: #4a4f57;
    --ink-faint: #767b84;
    --rule: #e0ddd6;
    --accent: #0f766e;
    --accent-soft: #e6f2f0;
    --accent-ink: #0b5750;
    --maxw: 47rem;
  }
  @media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
      color-scheme: dark;
      --bg: #121417;
      --bg-alt: #191c20;
      --surface: #1c2025;
      --ink: #eceef1;
      --ink-soft: #b3b9c2;
      --ink-faint: #878e99;
      --rule: #2b3037;
      --accent: #5eead4;
      --accent-soft: #16302d;
      --accent-ink: #99f6e4;
    }
  }
  :root[data-theme="dark"] {
    color-scheme: dark;
    --bg: #121417;
    --bg-alt: #191c20;
    --surface: #1c2025;
    --ink: #eceef1;
    --ink-soft: #b3b9c2;
    --ink-faint: #878e99;
    --rule: #2b3037;
    --accent: #5eead4;
    --accent-soft: #16302d;
    --accent-ink: #99f6e4;
  }

  * { box-sizing: border-box; }
  html { -webkit-text-size-adjust: 100%; scroll-behavior: smooth; }
  @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }

  body {
    margin: 0;
    background: var(--bg);
    color: var(--ink);
    font: 400 17px/1.65 ui-serif, Georgia, "Times New Roman", serif;
    -webkit-font-smoothing: antialiased;
  }

  .wrap { max-width: var(--maxw); margin: 0 auto; padding: 0 1.5rem; }

  h1, h2, h3, .ui {
    font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  }

  a { color: var(--accent-ink); text-decoration-thickness: 1px; text-underline-offset: 2px; }
  a:hover { text-decoration-thickness: 2px; }
  :focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; border-radius: 2px; }

  .skip {
    position: absolute; left: -9999px;
    background: var(--surface); color: var(--ink);
    padding: .6rem 1rem; border: 1px solid var(--rule); border-radius: 4px;
  }
  .skip:focus { left: 1rem; top: 1rem; z-index: 10; }

  /* ---------- masthead ---------- */
  header.top {
    border-bottom: 1px solid var(--rule);
    background: var(--bg);
    position: sticky; top: 0; z-index: 5;
    backdrop-filter: saturate(160%) blur(8px);
  }
  .top .wrap {
    display: flex; align-items: center; justify-content: space-between;
    gap: 1rem; padding-top: .85rem; padding-bottom: .85rem;
  }
  .brand {
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-weight: 640; font-size: 1.02rem; letter-spacing: -0.015em;
    color: var(--ink); text-decoration: none; display: flex; align-items: center; gap: .55rem;
  }
  .brand svg { flex: none; }
  nav.top-nav { display: flex; gap: 1.35rem; font-size: .87rem; }
  nav.top-nav a {
    font-family: ui-sans-serif, system-ui, sans-serif;
    color: var(--ink-soft); text-decoration: none;
  }
  nav.top-nav a:hover { color: var(--accent-ink); text-decoration: underline; }
  @media (max-width: 40rem) { nav.top-nav { display: none; } }

  /* ---------- hero ---------- */
  .hero { padding: 4.5rem 0 3.25rem; border-bottom: 1px solid var(--rule); }
  .eyebrow {
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: .74rem; font-weight: 600; letter-spacing: .1em; text-transform: uppercase;
    color: var(--accent-ink); margin: 0 0 1.1rem;
  }
  h1 {
    font-size: clamp(2.1rem, 6vw, 3.05rem); line-height: 1.1;
    letter-spacing: -0.03em; font-weight: 660; margin: 0 0 1.1rem;
  }
  .standfirst { font-size: clamp(1.1rem, 2.4vw, 1.29rem); color: var(--ink-soft); margin: 0 0 2rem; max-width: 36rem; }
  .motto {
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-weight: 600; font-size: 1.02rem; letter-spacing: -0.01em;
    border-left: 3px solid var(--accent); padding: .1rem 0 .1rem 1.1rem; margin: 0 0 2.1rem;
  }
  .cta { display: flex; flex-wrap: wrap; gap: .7rem; }
  .btn {
    font-family: ui-sans-serif, system-ui, sans-serif;
    display: inline-block; font-size: .92rem; font-weight: 550;
    padding: .68rem 1.15rem; border-radius: 6px; text-decoration: none; border: 1px solid var(--rule);
    color: var(--ink); background: var(--surface);
  }
  .btn:hover { border-color: var(--accent); color: var(--accent-ink); }
  .btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
  :root[data-theme="dark"] .btn-primary,
  .btn-primary { color: #fff; }
  @media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) .btn-primary { color: #06201d; }
  }
  :root[data-theme="dark"] .btn-primary { color: #06201d; }
  .btn-primary:hover { filter: brightness(1.08); color: inherit; }

  /* ---------- sections ---------- */
  section { padding: 3.4rem 0; border-bottom: 1px solid var(--rule); }
  section.tinted { background: var(--bg-alt); }
  h2 {
    font-size: clamp(1.45rem, 3.4vw, 1.8rem); line-height: 1.2;
    letter-spacing: -0.022em; font-weight: 640; margin: 0 0 1.15rem;
  }
  h3 {
    font-size: 1.03rem; font-weight: 620; letter-spacing: -0.01em;
    margin: 0 0 .5rem;
  }
  p { margin: 0 0 1.1rem; }
  p:last-child, ul:last-child { margin-bottom: 0; }
  .lede { font-size: 1.1rem; color: var(--ink-soft); }

  ul.plain { margin: 0 0 1.1rem; padding-left: 1.15rem; }
  ul.plain li { margin-bottom: .34rem; }

  /* pull quote / test */
  blockquote.test {
    margin: 1.6rem 0; padding: 1.3rem 1.5rem;
    background: var(--accent-soft); border-left: 3px solid var(--accent);
    border-radius: 0 6px 6px 0;
    font-size: 1.06rem; font-weight: 500; color: var(--ink);
  }
  blockquote.test p { margin: 0; }
  blockquote.test cite {
    display: block; margin-top: .7rem; font-style: normal;
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: .78rem; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-faint);
  }

  /* stat */
  .stat {
    display: flex; gap: 1.15rem; align-items: baseline;
    padding: 1.3rem 1.4rem; background: var(--surface);
    border: 1px solid var(--rule); border-radius: 8px; margin: 1.6rem 0;
  }
  .stat b {
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: clamp(2rem, 6vw, 2.6rem); font-weight: 640;
    letter-spacing: -0.03em; color: var(--accent-ink); line-height: 1; flex: none;
  }
  .stat span { font-size: .96rem; color: var(--ink-soft); }
  .stat span small { display: block; margin-top: .4rem; font-size: .8rem; color: var(--ink-faint); }

  /* flow diagram */
  figure.flow {
    margin: 1.7rem 0; padding: 1.5rem 1.2rem; background: var(--surface);
    border: 1px solid var(--rule); border-radius: 8px; overflow-x: auto;
  }
  figure.flow svg { display: block; margin: 0 auto; min-width: 300px; }
  figure.flow figcaption {
    font-family: ui-sans-serif, system-ui, sans-serif;
    margin-top: 1rem; font-size: .82rem; color: var(--ink-faint); text-align: center;
  }

  /* two-up cards */
  .duo { display: grid; grid-template-columns: 1fr 1fr; gap: 1.1rem; margin-top: 1.6rem; }
  @media (max-width: 38rem) { .duo { grid-template-columns: 1fr; } }
  .card {
    background: var(--surface); border: 1px solid var(--rule);
    border-radius: 8px; padding: 1.4rem 1.35rem;
  }
  .card .tag {
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: .7rem; font-weight: 600; letter-spacing: .09em; text-transform: uppercase;
    color: var(--accent-ink); display: block; margin-bottom: .45rem;
  }
  .card p { font-size: .95rem; color: var(--ink-soft); margin-bottom: .9rem; }
  .card a { font-family: ui-sans-serif, system-ui, sans-serif; font-size: .88rem; }

  /* principles */
  ol.principles {
    counter-reset: p; list-style: none; margin: 1.5rem 0 0; padding: 0;
    display: grid; grid-template-columns: 1fr 1fr; gap: .1rem 2rem;
  }
  @media (max-width: 38rem) { ol.principles { grid-template-columns: 1fr; } }
  ol.principles li {
    counter-increment: p; position: relative;
    padding: .7rem 0 .7rem 2.1rem; border-top: 1px solid var(--rule);
    font-size: .95rem;
  }
  ol.principles li::before {
    content: counter(p); position: absolute; left: 0; top: .78rem;
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: .74rem; font-weight: 600; color: var(--ink-faint);
    width: 1.4rem; text-align: right;
  }
  ol.principles b { font-family: ui-sans-serif, system-ui, sans-serif; font-weight: 620; font-size: .93rem; }
  ol.principles span { color: var(--ink-soft); }

  /* not-list */
  ul.nots {
    list-style: none; margin: 1.3rem 0 0; padding: 0;
    display: flex; flex-wrap: wrap; gap: .5rem;
  }
  ul.nots li {
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: .86rem; color: var(--ink-soft);
    background: var(--surface); border: 1px solid var(--rule);
    padding: .42rem .8rem; border-radius: 100px;
  }

  /* status steps */
  ol.phases { list-style: none; counter-reset: ph; margin: 1.5rem 0 0; padding: 0; }
  ol.phases li {
    counter-increment: ph; position: relative; padding: 0 0 1.15rem 2.4rem;
    border-left: 1px solid var(--rule); margin-left: .75rem;
  }
  ol.phases li:last-child { padding-bottom: 0; border-left-color: transparent; }
  ol.phases li::before {
    content: counter(ph); position: absolute; left: -0.78rem; top: 0;
    width: 1.55rem; height: 1.55rem; border-radius: 50%;
    background: var(--bg); border: 1px solid var(--rule); color: var(--ink-faint);
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: .76rem; font-weight: 600;
    display: flex; align-items: center; justify-content: center;
  }
  ol.phases li.now::before { background: var(--accent); border-color: var(--accent); color: #fff; }
  @media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) ol.phases li.now::before { color: #06201d; }
  }
  :root[data-theme="dark"] ol.phases li.now::before { color: #06201d; }
  ol.phases b { font-family: ui-sans-serif, system-ui, sans-serif; font-size: .95rem; font-weight: 620; }
  ol.phases span { display: block; font-size: .92rem; color: var(--ink-soft); }
  .now-tag {
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: .68rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
    color: var(--accent-ink); background: var(--accent-soft);
    padding: .12rem .45rem; border-radius: 3px; margin-left: .5rem; vertical-align: 2px;
  }

  /* questions */
  ul.questions { list-style: none; margin: 1.4rem 0 0; padding: 0; }
  ul.questions li {
    padding: .82rem 0; border-top: 1px solid var(--rule);
    font-size: .97rem; color: var(--ink-soft);
  }

  /* footer */
  footer { padding: 2.8rem 0 3.4rem; font-size: .9rem; color: var(--ink-faint); }
  footer .wrap > div { display: flex; flex-wrap: wrap; gap: 1.5rem; justify-content: space-between; }
  footer p { margin: 0 0 .55rem; }
  footer a { color: var(--ink-soft); }
  .domains { font-family: ui-sans-serif, system-ui, sans-serif; font-size: .85rem; }
  .domains a { display: block; margin-bottom: .35rem; text-decoration: none; }
  .domains a:hover { text-decoration: underline; color: var(--accent-ink); }
  .fineprint { margin-top: 2rem; padding-top: 1.4rem; border-top: 1px solid var(--rule); font-size: .82rem; line-height: 1.6; }
</style>
<style>
  /* This site uses the shared palette's own accent. */
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
