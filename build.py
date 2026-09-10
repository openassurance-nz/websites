#!/usr/bin/env python3
"""Build the three OpenAssurance sites.

Each site is served from its own domain, so none of them may fetch a stylesheet
from another: a cross-domain request would let one site observe the visitors of
another, which is exactly what the standard argues against. The shared design is
therefore shared at source level and inlined here, leaving three completely
self-contained pages that make no external requests at all.

Usage:  python build.py
"""

import io
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).parent
CSS = ROOT / "shared" / "site.css"
SITES = {
    "openassurance.html": "openassurance.nz",
    "opencompetency.html": "opencompetency.nz",
    "openprequal.html": "openprequal.nz",
}
PLACEHOLDER = "{{SITE_CSS}}"

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
# rel values that cause a fetch, as opposed to describing the document.
FETCHING_RELS = {
    "stylesheet", "icon", "shortcut icon", "apple-touch-icon", "mask-icon",
    "manifest", "preload", "prefetch", "preconnect", "dns-prefetch", "prerender",
}


def read(path):
    return io.open(path, encoding="utf-8").read()


def write(path, text):
    path.parent.mkdir(parents=True, exist_ok=True)
    io.open(path, "w", encoding="utf-8", newline="\n").write(text)


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


def main():
    css = read(CSS).rstrip("\n")
    failed = False

    for source_name, domain in SITES.items():
        page = read(ROOT / "src" / source_name)

        if PLACEHOLDER not in page:
            print("ERROR: src/%s has no %s placeholder" % (source_name, PLACEHOLDER))
            failed = True
            continue

        page = page.replace(PLACEHOLDER, css)
        write(ROOT / "dist" / domain / "index.html", page)
        size = len(page.encode("utf-8")) // 1024
        print("%-22s -> dist/%s/index.html  (%d KB)" % (source_name, domain, size))

    if failed:
        return 1

    for domain in SITES.values():
        for problem in external_requests(read(ROOT / "dist" / domain / "index.html")):
            print("ERROR: dist/%s would fetch: %s" % (domain, problem))
            failed = True

    print("no external requests in any built page" if not failed else "")
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
