# OpenAssurance websites

The three OpenAssurance sites:

| Site | Covers |
|---|---|
| [openassurance.nz](https://openassurance.nz) | The umbrella initiative: the problem, the exchange model, principles, status |
| [opencompetency.nz](https://opencompetency.nz) | The profile for people: competency, qualifications, attestations |
| [openprequal.nz](https://openprequal.nz) | The profile for organisations: prequalification, evidence, assessment |

The standard itself — charter, problem statement, privacy principles, architecture, discussion paper, and both profiles — lives in the [openassurance](https://github.com/openassurance-nz/openassurance) repository. This repository contains only the sites.

## Why one repository for three sites

The three sites share a design and a great deal of copy, so keeping them in one place is the only practical way to stop them drifting apart.

They cannot, however, share anything at runtime. Each site is served from its own domain, so a stylesheet fetched from one domain by another would let one site observe the other's visitors. That is precisely the kind of unnecessary data flow the standard argues against, so the design is shared at source level and inlined at build time instead.

Every built page is completely self-contained: no web fonts, no CDN scripts, no analytics, no third-party resources, no cookies. `build.py` fails if a built page would fetch anything at all.

## Layout

```text
shared/site.css        The design, shared by all three sites
src/*.html             Page sources, with {{SITE_CSS}} and {{SITE_ACCENT}} placeholders
build.py               Inlines the CSS, emits each site, checks for external requests
dist/<domain>/         Built output: one complete document root per site
DEPLOY.md              How to deploy to the current host
```

Per-site accent colours are defined once in `build.py` and applied to both the page and its 404. Everything else is common, so the three read as one family.

Each `dist/<domain>/` folder is a complete document root — `index.html`, `404.html`, `.htaccess`, `robots.txt`, `sitemap.xml` — ready to upload as-is.

`dist/` is committed. Any static host can serve it directly, and nobody needs to run a build to publish a change.

## Building

```
python build.py
```

No dependencies beyond Python 3. The build fails if a source file loses its `{{SITE_CSS}}` placeholder, or if a built page would make an external request on load.

That last check is not decoration. The `.htaccess` it emits sets a Content-Security-Policy forbidding all external resources, so a page that acquired one would break in the browser rather than quietly phone home. Build-time and runtime enforce the same rule.

## Deploying

See [DEPLOY.md](DEPLOY.md).

## Local preview

```
python -m http.server 8000 --directory dist
```

Then open http://localhost:8000/openassurance.nz/.

## Content rules

Site copy is drawn from the standards repository and must stay consistent with it:

- the founding principles must match `CHARTER.md`;
- factual claims about third parties must stay sourced, as they are in `docs/discussion-paper.md`;
- no vendor-specific or organisation-specific content, per `CONTRIBUTING.md`;
- references to external organisations must not imply consultation or endorsement;
- worked examples are illustrative and must not assert what any organisation should require.

## Licence

Apache License 2.0, as with the standards repository.
