# Third-party notices

wcag-scout's own code is licensed under the [MIT licence](LICENSE). It depends on, bundles, or (in
the Docker image) ships the following third-party software.

## Bundled in this repository

| Component | Version | Licence | Where |
|---|---|---|---|
| [axe-core](https://github.com/dequelabs/axe-core) | 4.14.0 | MPL-2.0 | `resources/axe/axe.min.js`, `resources/axe/locales/de.json` |
| Libraries compiled into axe-core (colorjs.io, core-js-pure, css-selector-parser, doT.js, …) | | MIT | inside `axe.min.js`, see `resources/axe/LICENSE-3RD-PARTY.txt` |

### Why MPL-2.0 is compatible with an MIT project

MPL-2.0 is a *file-level* copyleft licence. It applies to the axe-core files themselves, not to
the code that uses them ("Larger Work", MPL-2.0 section 3.3). wcag-scout complies by:

1. keeping the axe-core files **unmodified** and in their own directory,
2. shipping the **full licence text** next to them (`resources/axe/LICENSE`),
3. stating **where the source code is available** (`resources/axe/README.md`: tag, tarball and integrity hash),
4. naming axe-core and its licence in every generated report.

If the axe-core files were ever modified, those modifications would have to be published under
MPL-2.0. wcag-scout's own MIT-licensed code is not affected either way.

Text taken from axe-core at runtime (rule descriptions, help texts, German locale) appears in
generated reports and is attributed there.

## Composer dependencies (runtime)

All runtime dependencies are under permissive licences. `composer licenses` (run in CI by
`bin/check-licenses`) fails the build if a dependency with a licence outside this allow list
appears: MIT, BSD-2-Clause, BSD-3-Clause, ISC, Apache-2.0.

| Package | Licence | Purpose |
|---|---|---|
| chrome-php/chrome (+ chrome-php/wrench) | MIT | Chrome DevTools Protocol client |
| symfony/console, http-client, translation (+ their symfony/* and psr/* dependencies) | MIT | CLI, HTTP, i18n |
| twig/twig | BSD-3-Clause | HTML report templates |
| monolog/monolog, psr/log (pulled in by chrome-php) | MIT | Logging interfaces |

The exact, current list: `composer licenses --no-dev`.

## Development dependencies (not distributed)

PHPUnit (BSD-3-Clause), PHPStan and its extensions (MIT), PHP-CS-Fixer (MIT).

## Docker image only

The image is built from the official `php` image (Debian) and adds Debian packages. They are
not part of this repository and keep their own licences, available inside the image under
`/usr/share/doc/*/copyright`.

| Component | Licence |
|---|---|
| PHP | PHP License 3.01 |
| Chromium | BSD-3-Clause and various third-party licences (`chrome://credits`) |
| Liberation fonts | SIL Open Font License 1.1 |
| DejaVu fonts | Bitstream Vera / Arev licence (permissive) |
