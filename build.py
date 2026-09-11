#!/usr/bin/env python3
"""Build the three OpenAssurance sites.

Each site is served from its own domain, so none of them may fetch a stylesheet
from another: a cross-domain request would let one site observe the visitors of
another, which is exactly what the standard argues against. The shared design is
therefore shared at source level and inlined here, leaving three completely
self-contained pages that make no external requests at all.

Output is deploy-ready for ordinary Apache/LiteSpeed shared hosting: each
dist/<domain>/ folder is the document root for that domain, uploaded as-is.

Usage:  python build.py
"""

import io
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).parent
CSS = ROOT / "shared" / "site.css"

SITES = {
    "openassurance.html": {
        "domain": "openassurance.nz",
        "name": "OpenAssurance",
        # The umbrella site uses the palette's own accent; the profiles override it.
        "accent": None,
        "holding_anchor": None,
    },
    "opencompetency.html": {
        "domain": "opencompetency.nz",
        "name": "OpenCompetency",
        "accent": ("#1d4ed8", "#e6edfd", "#1b47b4", "#93b4fd", "#1a2440", "#bfd0fe"),
        "holding_anchor": "#profiles",
    },
    "openprequal.html": {
        "domain": "openprequal.nz",
        "name": "OpenPrequal",
        "accent": ("#a1571a", "#f6ede2", "#8a4a15", "#eab676", "#33261a", "#f3cfa2"),
        "holding_anchor": "#profiles",
    },
}

# Where the holding redirects point while the profile sites are not yet live.
HOLDING_TARGET = "openassurance.nz"

# Domains parked on the umbrella site as cPanel Aliases for now. Empty this
# list once each one has a document root of its own.
HELD_ALIASES = ("opencompetency.nz", "openprequal.nz")

PLACEHOLDER = "{{SITE_CSS}}"
ACCENT_PLACEHOLDER = "{{SITE_ACCENT}}"

# Patterns that make a browser fetch something on load. Deliberately excluded:
# href on an <a> (the visitor has to click it), an xmlns namespace URI (it names
# an XML vocabulary and is never dereferenced), and <link rel="canonical"> or
# "alternate" (metadata for crawlers, not a resource to load).
FETCHERS = (
    re.compile(r"""<(?:script|img|iframe|video|audio|source|embed|track)[^>]+src\s*=\s*["'](https?:)?//""", re.I),
    re.compile(r"""@import""", re.I),
    re.compile(r"""url\(\s*["']?(https?:)?//""", re.I),
    re.compile(r"""<form[^>]+action\s*=\s*["'](https?:)?//""", re.I),
)

LINK_TAG = re.compile(r"<link\b[^>]*>", re.I)
LINK_REL = re.compile(r"""rel\s*=\s*["']([^"']+)["']""", re.I)
LINK_HREF = re.compile(r"""href\s*=\s*["']((?:https?:)?//[^"']*)["']""", re.I)
FETCHING_RELS = {
    "stylesheet", "icon", "shortcut icon", "apple-touch-icon", "mask-icon",
    "manifest", "preload", "prefetch", "preconnect", "dns-prefetch", "prerender",
}


def read(path):
    return io.open(path, encoding="utf-8").read()


def write(path, text):
    path.parent.mkdir(parents=True, exist_ok=True)
    io.open(path, "w", encoding="utf-8", newline="\n").write(text)


def accent_css(accent):
    """Per-site accent overrides, applied after the shared palette."""
    if accent is None:
        return "  /* This site uses the shared palette's own accent. */"
    light_a, light_soft, light_ink, dark_a, dark_soft, dark_ink = accent
    light = "--accent: %s; --accent-soft: %s; --accent-ink: %s;" % (light_a, light_soft, light_ink)
    dark = "--accent: %s; --accent-soft: %s; --accent-ink: %s;" % (dark_a, dark_soft, dark_ink)
    return "\n".join([
        "  /* --- site identity ---------------------------------------------------- */",
        "  :root { %s }" % light,
        "  @media (prefers-color-scheme: dark) {",
        "    :root:not([data-theme=\"light\"]) { %s }" % dark,
        "  }",
        "  :root[data-theme=\"dark\"] { %s }" % dark,
    ])


def external_requests(page):
    """Yield a readable excerpt for anything the page would fetch on load."""
    for pattern in FETCHERS:
        for match in pattern.finditer(page):
            start = max(0, match.start() - 15)
            excerpt = page[start:match.end() + 40].replace("\n", " ")
            yield " ".join(excerpt.split())

    for tag in LINK_TAG.finditer(page):
        rel = LINK_REL.search(tag.group(0))
        href = LINK_HREF.search(tag.group(0))
        if href and rel and rel.group(1).strip().lower() in FETCHING_RELS:
            yield "%s (rel=%s)" % (href.group(1), rel.group(1))


def htaccess(domain, held_aliases=()):
    """Apache/LiteSpeed config for shared hosting.

    held_aliases are domains parked onto this one as cPanel Aliases while their
    own sites are not live yet. They arrive with their own Host header and are
    sent on with a 302, so they can become independent sites later without
    fighting a cached permanent redirect.

    HSTS is left commented out deliberately: enabling it before HTTPS is
    confirmed working on the domain makes the site unreachable for the duration
    of the max-age, and browsers honour it even after it is removed.
    """
    alias_block = ""
    if held_aliases:
        names = "|".join(a.split(".")[0] for a in held_aliases)
        alias_block = """
  # --- TEMPORARY ---------------------------------------------------------
  # These domains are parked on this account as cPanel Aliases until they
  # get sites of their own. R=302, never 301: a permanent redirect would be
  # cached by browsers and search engines long after those sites went live.
  # Delete this block when %(list)s move to their own document roots.
  # NE (noescape) is required. Without it mod_rewrite percent-encodes the
  # substitution, turning the "#" into "%%23" — a literal path segment that
  # 404s, rather than a fragment.
  # This redirect must never be cached, or a browser replays it long after
  # the target changes. Cache policy below is scoped to real files only, so
  # this response goes out with no Cache-Control at all and stays fresh.
  RewriteCond %%{HTTP_HOST} ^(www\\.)?(%(names)s)\\.nz$ [NC]
  RewriteRule ^ https://%(domain)s/#profiles [L,NE,R=302]
  # --- end TEMPORARY -----------------------------------------------------
""" % {"names": names, "domain": domain, "list": " and ".join(held_aliases)}

    return """# %(domain)s — static site, no PHP required.

DirectoryIndex index.html
Options -Indexes
ErrorDocument 404 /404.html

<IfModule mod_rewrite.c>
  RewriteEngine On

  # Certificate validation must never be redirected. cPanel AutoSSL, Let's
  # Encrypt and DigiCert all validate by fetching a file under /.well-known/
  # on each domain — acme-challenge/ or pki-validation/ depending on the
  # provider — and must receive the file itself. A redirect fails issuance and
  # silent renewal, and the error it produces does not point back here.
  # Keep this rule first, and exclude the whole directory so a change of
  # certificate provider does not quietly break renewal.
  RewriteRule ^\\.well-known/ - [L]
%(alias_block)s
  # Canonical host: https, no www, and not any alias parked on this account.
  RewriteCond %%{HTTPS} !=on [OR]
  RewriteCond %%{HTTP_HOST} !^%(escaped)s$ [NC]
  RewriteRule ^ https://%(domain)s%%{REQUEST_URI} [L,R=301]
</IfModule>

<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "DENY"

  # Outbound links carry no referrer, so linked sites learn nothing about
  # where the visitor came from.
  Header always set Referrer-Policy "no-referrer"
  Header always set Permissions-Policy "geolocation=(), camera=(), microphone=(), payment=(), usb=()"

  # The pages are entirely self-contained, so everything external can be
  # forbidden outright. This enforces at the server what build.py checks at
  # build time: scripts are banned, styles and images may only be inline.
  Header always set Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'; img-src data:; base-uri 'none'; form-action 'none'; frame-ancestors 'none'"

  # Cache policy lives here rather than in mod_expires, which cannot be made
  # conditional. mod_expires writes to a different header table, so a
  # "Header always set" beside it produces two conflicting Cache-Control
  # headers rather than replacing one. FilesMatch only matches real files, so
  # a redirect response never picks this up and stays uncacheable — which is
  # what a temporary redirect has to be.
  <FilesMatch "\\.(html|txt|xml)$">
    Header set Cache-Control "max-age=600"
  </FilesMatch>

  # Enable only once HTTPS is confirmed working on this domain. Browsers
  # remember it, so a premature one is hard to undo.
  # Header always set Strict-Transport-Security "max-age=31536000"
</IfModule>

<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css text/plain text/xml image/svg+xml
</IfModule>

<IfModule mod_expires.c>
  ExpiresActive On

  # Images only. HTML cache policy is set in the mod_headers block above, so
  # that redirect responses are never given one.
  ExpiresByType image/svg+xml "access plus 1 week"
</IfModule>
""" % {"domain": domain, "alias_block": alias_block, "escaped": domain.replace(".", r"\.")}


def holding_htaccess(site, target, anchor):
    """Temporary redirect for a domain whose own site is not live yet.

    R=302, not 301. A permanent redirect is cached hard by browsers and search
    engines; this domain is going to become its own site, and visitors who
    cached a 301 would keep being bounced away from it long after it existed.
    """
    return """# %(domain)s — temporary holding redirect to %(target)s.
#
# This domain will become its own site. Until then, send visitors to the
# umbrella site so the name resolves to something real.
#
# R=302 is deliberate. Do not "tidy" it into a 301: browsers and search
# engines cache permanent redirects aggressively, and this one is temporary.

DirectoryIndex index.html
Options -Indexes

<IfModule mod_rewrite.c>
  RewriteEngine On

  # Certificate validation must never be redirected. Keep this rule first.
  RewriteRule ^\\.well-known/ - [L]

  # Everything else, including www and any path, goes to the umbrella site.
  # NE (noescape) keeps the "#" a fragment instead of a percent-encoded path.
  RewriteRule ^ https://%(target)s/%(anchor)s [L,NE,R=302]
</IfModule>

<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set Referrer-Policy "no-referrer"
</IfModule>
""" % {"domain": site["domain"], "target": target, "anchor": anchor}


def holding_page(site, target, anchor, css, accent):
    """Fallback for hosts without mod_rewrite. Normally never seen."""
    return """<!doctype html>
<html lang="en-NZ">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>%(name)s — part of OpenAssurance</title>
<meta name="robots" content="noindex">
<meta http-equiv="refresh" content="0; url=https://%(target)s/%(anchor)s">
<link rel="canonical" href="https://%(target)s/">
<style>
%(css)s
%(accent)s
  main.holding { padding: 6rem 0 7rem; }
</style>
</head>
<body>
<main class="holding">
  <div class="wrap">
    <p class="eyebrow">%(name)s</p>
    <h1 style="font-size:clamp(1.8rem,5vw,2.4rem)">This site is on its way.</h1>
    <p class="standfirst">
      %(name)s is a profile of OpenAssurance. Until it has a site of its own,
      you will find it described on the main site.
    </p>
    <div class="cta">
      <a class="btn btn-primary" href="https://%(target)s/%(anchor)s">Continue to %(target)s</a>
    </div>
  </div>
</main>
</body>
</html>
""" % {"name": site["name"], "target": target, "anchor": anchor, "css": css, "accent": accent}


def robots(domain):
    return """User-agent: *
Allow: /

Sitemap: https://%s/sitemap.xml
""" % domain


def sitemap(domain):
    return """<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://%s/</loc>
    <changefreq>monthly</changefreq>
  </url>
</urlset>
""" % domain


def not_found(site, css, accent):
    return """<!doctype html>
<html lang="en-NZ">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Page not found — %(name)s</title>
<meta name="robots" content="noindex">
<style>
%(css)s
%(accent)s
  main.notfound { padding: 6rem 0 7rem; }
</style>
</head>
<body>
<main class="notfound">
  <div class="wrap">
    <p class="eyebrow">404</p>
    <h1 style="font-size:clamp(1.8rem,5vw,2.4rem)">That page isn't here.</h1>
    <p class="standfirst">%(name)s is a single page, so there is not much to get lost in.</p>
    <div class="cta">
      <a class="btn btn-primary" href="/">Go to %(domain)s</a>
      <a class="btn" href="https://github.com/openassurance-nz/openassurance">View on GitHub</a>
    </div>
  </div>
</main>
</body>
</html>
""" % {"name": site["name"], "domain": site["domain"], "css": css, "accent": accent}


def main():
    css = read(CSS).rstrip("\n")
    failed = False

    for source_name, site in SITES.items():
        domain = site["domain"]
        page = read(ROOT / "src" / source_name)

        if PLACEHOLDER not in page:
            print("ERROR: src/%s has no %s placeholder" % (source_name, PLACEHOLDER))
            failed = True
            continue

        accent = accent_css(site["accent"])
        page = page.replace(PLACEHOLDER, css).replace(ACCENT_PLACEHOLDER, accent)

        out = ROOT / "dist" / domain
        write(out / "index.html", page)
        write(out / "404.html", not_found(site, css, accent))
        aliases = HELD_ALIASES if domain == HOLDING_TARGET else ()
        write(out / ".htaccess", htaccess(domain, aliases))
        write(out / "robots.txt", robots(domain))
        write(out / "sitemap.xml", sitemap(domain))

        size = len(page.encode("utf-8")) // 1024
        print("%-22s -> dist/%s/  (index %d KB, + 404, .htaccess, robots, sitemap)"
              % (source_name, domain, size))

        # A holding version for domains whose own site is not live yet.
        if site.get("holding_anchor") is not None:
            hold = ROOT / "holding" / domain
            write(hold / ".htaccess", holding_htaccess(site, HOLDING_TARGET, site["holding_anchor"]))
            write(hold / "index.html", holding_page(site, HOLDING_TARGET, site["holding_anchor"], css, accent))
            print("%-22s -> holding/%s/  (302 to %s)" % ("", domain, HOLDING_TARGET))

    if failed:
        return 1

    checks = []
    for site in SITES.values():
        for filename in ("index.html", "404.html"):
            checks.append(ROOT / "dist" / site["domain"] / filename)
        if site.get("holding_anchor") is not None:
            checks.append(ROOT / "holding" / site["domain"] / "index.html")

    for path in checks:
        for problem in external_requests(read(path)):
            print("ERROR: %s would fetch: %s" % (path.relative_to(ROOT), problem))
            failed = True

    if not failed:
        print("no external requests in any built page")
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
