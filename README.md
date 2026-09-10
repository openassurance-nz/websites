# openassurance.nz

The website for [OpenAssurance](https://github.com/openassurance-nz/openassurance), an early-stage New Zealand open standards initiative for exchanging workplace assurance information.

The standard itself — charter, problem statement, privacy principles, architecture, discussion paper, and the OpenCompetency and OpenPrequal profiles — lives in the [openassurance](https://github.com/openassurance-nz/openassurance) repository. This repository contains only the site.

## Structure

`index.html` is the entire site: a single self-contained file.

It loads no web fonts, CDN scripts, analytics, or third-party resources, and sets no cookies. A project arguing that organisations should hold fewer copies of personal information should not ship a tracker. Please keep it that way.

## Local preview

Any static file server will do:

```
python -m http.server 8000
```

Then open http://localhost:8000.

## Content

Site copy is drawn from the standards repository and must stay consistent with it. In particular:

- the founding principles must match `CHARTER.md`;
- factual claims about third parties must remain sourced, as they are in `docs/discussion-paper.md`;
- no vendor-specific or organisation-specific content, per `CONTRIBUTING.md`;
- references to external organisations must not imply consultation or endorsement.

## Licence

Apache License 2.0, as with the standards repository.
