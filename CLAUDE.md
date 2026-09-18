# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository. The conventions shared by every darvis package (language, releases, CI, docs site, Boost guidelines, public API policy) are in [../CLAUDE.md](../CLAUDE.md); this file only holds what is specific to this package.

## Package overview

`darvis/mkg-client` is a PHP client (PHP 8.2+) for the REST API of MKG Software, the Dutch ERP system. It handles the Tomcat form login and the `JSESSIONID` session, and offers a typed service per MKG document. The client is framework-agnostic and uses plain Guzzle; Laravel gets a service provider with auto-discovery, and `laravel/framework` is only a dev dependency for the Testbench suite. Keep it that way: no `Illuminate\*` import outside `src/Laravel/`, `Config/LaravelConfigProvider.php` and `Cookies/LaravelCookieStore.php`.

- Namespace: `Darvis\MkgClient\` → `src/`
- Service provider: [DarvisMkgClientProvider](src/Laravel/Providers/DarvisMkgClientProvider.php), auto-registered via `extra.laravel.providers`
- Config key: `mkg`, file in [config/mkg.php](config/mkg.php); `darvis/mkg-api` uses the same key, so the two packages cannot share one host app

## Commands

```bash
composer test                 # Pest suite
vendor/bin/pest --filter "retries once"
composer lint                 # Pint (check only); composer format fixes
composer analyse              # Larastan, level 8; missingType.iterableValue is ignored until the service methods carry array shapes
```

## Architecture

- Every service extends [BaseMkgService](src/BaseMkgService.php), which owns the Guzzle client, the login, the JSON requests and the shared list query (`FieldList`, `Filter`, `NumRows`, `Sort`, `SkipRows`). Services only add the document paths and the typed finders; `find…Rows…` methods return flat rows, the plain `find…` methods the raw MKG response.
- Config is read through a `ConfigProviderInterface`. The default is a [ChainConfigProvider](src/Config/ChainConfigProvider.php) of `LaravelConfigProvider` (which is the only place that calls `config()`) and `EnvConfigProvider`; plain PHP passes an `ArrayConfigProvider`. Don't read config anywhere else.
- URLs are derived in [MkgEndpoints](src/Support/MkgEndpoints.php): `https://{host}/{client_path}/web/v3/MKG/Documents` and `…/static/auth/j_spring_security_check`. `url_auth` and `url_prod` only override them. The retired `/mkg/rest/v1` and the plausible `/mkg/rest/v3` answer with a Tomcat HTML `403`, which is why the README says what a `403` means.
- [SessionCookieManager](src/Support/SessionCookieManager.php) logs in and keeps the cookie in a `CookieStoreInterface`: `LaravelCookieStore` (`storage/mkg/cookie.txt` through the Storage facade) when Laravel is present, otherwise `FileCookieStore`. A `401` (JSON body, `Not authenticated`) drops the cookie, logs in again and retries once. A `403` is never retried; it throws [MkgHttpException](src/Exceptions/MkgHttpException.php) with an explanation that names the configured base. Adding `403` to the retry doubles the traffic against a customer's ERP and still fails.
- [RequestLogger](src/Support/RequestLogger.php) is a Guzzle middleware: every call at debug level when `log_requests` is on, and a warning for any call slower than `slow_request_seconds` even when it is off. Laravel's HTTP client profilers never see MKG traffic, which is the reason it exists.
- Field metadata comes from the CSV files in `resources/csv/` (one per MKG document, semicolon separated, exported from MKG), loaded by [FieldMetaNormalizer](src/Support/FieldMetaNormalizer.php). It drives type normalisation (dates, numbers, booleans) and filters the default `FieldList`: a field missing from the CSV is dropped silently. A host app can override a file under `storage/mkg/`. MKG rows arrive as `response.ResultData[0].{document}`.
- MKG caps a result at 1000 rows and defaults to 100 without `NumRows`, with no error when more exist. `SkipRows` works even though MKG's documentation does not list it; `SkipRows=0` is a valid offset, so it is checked against `null`, not falsiness.

## Conventions

- Tests run on Orchestra Testbench with Guzzle's `MockHandler`; no test may reach an MKG installation. `tests/TestCase.php` sets `url_auth` and `url_prod` explicitly; a test for the derived URLs clears them.
- MKG document and field names stay as MKG spells them (`vorh`, `debi_num`, `vorh_dat_order`), also in docs and examples. The Dutch variant titles in `MKG_VARIANT_TITLES` are MKG's own labels, not translations to change.
- Keep the public API compatible within 1.x: the service classes and their public methods, the config keys and environment variables, the config providers, the cookie stores, `MkgHttpException` and the published CSV files.
