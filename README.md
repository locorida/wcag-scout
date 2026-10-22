# wcag-scout

Polite accessibility crawler: audits public websites with [axe-core](https://github.com/dequelabs/axe-core)
in headless Chromium and writes WCAG 2.2 / EN 301 549 reports (HTML, PDF, JSON, JUnit).

> Work in progress. Full documentation follows with the first release.

## Development

```powershell
.\scripts\dev.ps1 composer install
.\scripts\dev.ps1 composer ci        # code style, static analysis, licences, unit tests
```

## License

[MIT](LICENSE). Bundled axe-core: MPL-2.0, see [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
