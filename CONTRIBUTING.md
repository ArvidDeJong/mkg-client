# Contributing

Contributions are welcome: bug reports, fixes, documentation and ideas.

## Before you start

- **Bugs:** open an [issue](https://github.com/ArvidDeJong/mkg-client/issues/new/choose) with the steps to reproduce.
- **Features:** open an issue first, so we can agree it fits before you build it.
- **Security issues:** don't open an issue; see [SECURITY.md](SECURITY.md).

## Development

```bash
git clone https://github.com/ArvidDeJong/mkg-client.git
cd mkg-client
composer install

composer test      # Pest
composer lint      # Pint, check only (composer format fixes)
composer analyse   # Larastan, level 8
```

CI runs the tests on PHP 8.2 to 8.4 with Laravel 11, 12 and 13, on the lowest and the latest dependencies. The client itself stays framework-agnostic; Laravel is only a dev dependency for the Testbench suite.

## Pull requests

- Add or update tests for every change in behaviour. Tests use Guzzle's `MockHandler`; no test may reach an MKG installation.
- Keep the public API compatible within 1.x: the service classes and their methods, the config keys and environment variables, the config providers and cookie stores, `MkgHttpException`. Deprecate first and remove in 2.0.
- Never retry on a `403`: it is Tomcat saying the URL path is wrong, not a session problem. Only a `401` triggers a fresh login, once.
- Keep the field metadata in `resources/csv/` in sync with the MKG documents the services use; a field that is missing there is silently dropped from `FieldList`.
- Write code, comments, messages and docs in English. MKG's own document and field names (`vorh`, `debi_num`) stay as MKG spells them.
- Update `docs/`, `CHANGELOG.md` (under `Unreleased`) and `resources/boost/` when users will notice the change.
- The documentation in `docs/` is also the website. Don't write `{{ }}` or `{% %}` in code examples; Jekyll would render it.

## Code of conduct

This project follows the [Contributor Covenant](https://github.com/ArvidDeJong/.github/blob/main/CODE_OF_CONDUCT.md).
