# Changelog

All notable changes to this project. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions follow
[Semantic Versioning](https://semver.org/).

## [0.1.0] - 2026-10-22

First release.

### Added
- `audit` command: audits URLs, URL lists or sitemaps (including sitemap indexes and
  `.xml.gz`) with axe-core 4.14.0 in headless Chromium; optional link following within the site.
- Polite crawling: robots.txt per RFC 9309 including `Crawl-delay`, a pause between
  requests, a page limit, its own user agent with contact URL. Requests other than
  GET/HEAD/OPTIONS are blocked, and pages cannot navigate away after loading.
- Findings mapped to WCAG 2.2 criteria and levels and to EN 301 549 V3.2.1 (default) or
  V4.1.1, with severity, selector, HTML excerpt and element screenshot.
- Reports: accessible HTML, tagged PDF, JSON (result of record), JUnit XML; English and
  German; plain-language explanations for 34 rules; disclaimer and manual checklist in
  every format.
- `baseline` command and `--baseline`: only new findings fail a run.
- `compare` command: fixed, new, unchanged and "not checked again" findings between two runs.
- `render` command: rebuilds reports from JSON, e.g. in another language.
- Docker images on the GitHub Container Registry and a GitHub Action.
- Template repository for auditing a website from your own GitHub repository:
  <https://github.com/locorida/wcag-scout-template>.

### Fixed (before the release)
- Chromium did not start when the user has no writable home directory (e.g. `docker run --user`).
- `<image:loc>` and other sitemap extension entries were treated as pages.

[0.1.0]: https://github.com/locorida/wcag-scout/releases/tag/v0.1.0
