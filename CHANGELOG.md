# Changelog

All notable changes to `darvis/mkg-client` will be documented in this file.

## [Unreleased]

Documentation only; nothing in the package changes.

### Added

- A Testing page in the documentation: how to test code that uses the package with a Guzzle `MockHandler`, a complete Pest example, and the responses to queue for rows, no rows, a login, an expired session, a wrong URL path, a redirect and a `5xx`.
- A "Check that it works" step on the Installation page: one `php artisan tinker` command, the output to expect, and what every other outcome means.
- A complete example on the Usage page (an Artisan command that shows a debtor and its latest orders), with its file path, imports and exception handling.
- A Troubleshooting entry for Laravel 11. The Laravel 11 container passes its own default Guzzle client into a service that is resolved with `app()` or type-hinted, so `MKG_TIMEOUT`, `MKG_CONNECT_TIMEOUT`, `MKG_VERIFY_SSL` and `MKG_LOG_REQUESTS` have no effect there and redirects are followed. The page has the binding that avoids it. Laravel 12 and 13 are not affected.

### Fixed

- The documentation said the CSV metadata gives "type normalisation (dates, numbers, booleans)". Only `integer`, `decimal`, `percentage`, `bedrag`, `logical`, `datum` and the text types are converted. Quantities (`aantal`) and types such as `tijdstip`, `week` and `telefoonnummer` come back as MKG sent them, and `debi_num` and `vorh_num` are `character` fields, so strings. The Usage page now has the table.
- The documentation said "a field missing from the CSV is dropped silently". That is only true for the built-in default field lists of `ArticleService`, `DebtorsService`, `ContactpersonService` and `UserService`. A `fieldList` you pass is sent as written, and a field in it that the CSV does not know comes back unconverted.
- The Troubleshooting page said a missing `host` without the two URL overrides throws when the service is created. The constructor only checks the login URL: with `MKG_URL_AUTH` set and no `MKG_HOST` or `MKG_URL_PROD`, the service is created and the first request throws the `RuntimeException`.
- The Usage page named only `MkgHttpException` and did not say what else a call throws. A `5xx`, a timeout, a connection error and a `4xx` or `5xx` on the login call are Guzzle's own exceptions; catch `GuzzleHttp\Exception\GuzzleException` after `MkgHttpException`.
- The documentation did not say that the default field list of `OrdersService`, `AddressesService` and `RelationsService` is every field in the CSV (158 for `vorh`, 355 for `vorr`). It now says how to pass a `fieldList`.
- The documentation did not say that a Guzzle client you pass yourself bypasses `verify_ssl`, `timeout`, `connect_timeout`, `log_requests`, `slow_request_seconds` and the redirect setting, that services are not singletons, or that a plain PHP project with `illuminate/support` installed needs `cookieStore: new FileCookieStore`.
- The documentation did not say which lookups refuse an empty value. The lookups by number throw an `InvalidArgumentException`; the searches on name, email, article code and user code and the combined searches return `[]` without a request.
- The Verification page logged in without the `X-CustomerID` header, which the package does send, and used `$MKG_URL_AUTH` and `$MKG_URL_PROD` without saying how to set them. The steps that the package itself never performs (the `/User` call and the write test with a `PUT`) are removed: the package only reads.
- The Boost skill said the combined debtor search merges "on RowKey". `RowKey` is not in the CSV, so it is not requested by default; the rows are merged without duplicates.
- `docs/README.md`, a second index next to the site's home page, is removed. The README now has the standard sections, with `Requirements` and without a personal author section.

## [1.3.0] - 2026-09-21

### Security

- **A value passed to a number lookup could change the MKG filter.** `findHeaderByOrderNumber()`, `findRowsByOrderNumber()`, `findRowParametersByOrderNumber()`, `findByDebtorNumber()`, `findByRelationNumber()`, `findByAddressNumber()`, `findByContactpersonNumber()` and their `…Rows…` variants placed their argument in the `Filter` as it was, so a value with spaces and an operator became part of the filter expression and could return other rows than the one asked for. A plain number (`10001`, `-5`, `12.50`) and a plain key of letters and digits (`VK2606096`) still go in unquoted, exactly as before, so an existing integration sends the same filter. Every other value (a space, a hyphen, a quote, an operator, a bare word without a digit) is now compared as a quoted and escaped text (`vorh_num = "VK-2606096"`), and an empty value throws an `InvalidArgumentException` before a request is sent. What to do: nothing for keys of letters and digits. If your keys hold other characters, check one lookup against your MKG installation after upgrading. If your application passes visitor input to these methods, catch the `InvalidArgumentException` or validate the input first.
- **A backslash could break out of a quoted filter value.** The text lookups (`findByArticleCode()`, `findByArticleName()`, `findByDebtorName()`, `findByDebtorEmail()`, `findByUserCode()` and the like) escaped the quote but not the backslash, so a value ending in a backslash took the closing quote with it. The backslash is now escaped first, then the quote, and a value with a control character (a line break, a tab, a NUL byte) throws an `InvalidArgumentException`. An ordinary value is sent exactly as before. What to do: nothing, unless you search with values that contain a backslash; those now match the literal backslash.
- **A primary key could change the URL of the request.** `OrdersService::getHeaderByPrimaryKey()` and `getRowByPrimaryKey()`, `AddressesService::getByPrimaryKey()` (key and `$document`), `AddressesService::list()` and its finders (`$document`), and `RelationsService::getByPrimaryKey()` and `findByDebtorNumber()` put their arguments into the URL path unencoded, so a `/`, `..`, `?` or `#` in a key reached another document or added query parameters. Every part is now URL-encoded, and an empty part, `.` and `..` throw an `InvalidArgumentException`. Ordinary keys give the same URL as before, including a whole composite key such as `1+10001`, which keeps its `+`. What to do: nothing.
- **A redirect or a login page looked like an empty result.** A `3xx` answer (the client never follows redirects) and a `2xx` answer whose body is not JSON came back as an empty array, without an exception and without a log line. A sync could not tell that from "no rows" and could conclude that every order or debtor was gone. Both now throw `MkgHttpException` with the status code, and are logged as the warning `MKG response was not usable.`. The message does not contain the body or the `Location` header. A genuine empty result (`[]`, `{}`, or an envelope without rows) is still an empty array, and the single retry after a `401` works as before. What to do: make sure a sync catches `MkgHttpException` (or Guzzle's `ClientException`, which it extends) and stops instead of continuing; code that relied on `[]` for an unreachable MKG now gets the exception.
- **The session cookie file in plain PHP was readable by other users of the server.** `FileCookieStore` created its directory with mode `0777` and the file with the default umask, by default in the shared system temp directory. A new directory is now created with `0700` and the file with `0600`, written to a temporary file and renamed into place, so it is never half written or briefly readable. An existing file is tightened on the next login. What to do: delete a directory that an older version created in the temp directory (`rm -r /tmp/mkg`) so it is created again with the new mode, and preferably set `mkg.cookie_storage_path` to a directory that only your application user can write to. Laravel applications use `LaravelCookieStore` and are not affected.

### Changed

- A `3xx` answer and a `2xx` answer that is not JSON throw `MkgHttpException` where they used to return an empty array. See Security above.
- A number lookup with a value that is neither a plain number nor a plain key of letters and digits sends a quoted filter (`vorh_num = "VK-2606096"`) where it used to send the value unquoted, and throws an `InvalidArgumentException` on an empty value. `vorh_num = VK2606096` and `debi_num = 10001` are sent as before.
- `RequestLogger::unusableResponse()` logs the new warning `MKG response was not usable.` with `method`, `path`, `status` and `reason`.

### Fixed

- The documentation, the Boost guideline and `CLAUDE.md` said that the Laravel cookie store writes to `storage/mkg/cookie.txt`. It writes through the `Storage` facade to the default filesystem disk (`mkg/cookie.txt` under the root of that disk); that disk must not be public. The behaviour did not change.

## [1.2.1] - 2026-09-21

### Added

- Laravel Boost skill `mkg-client-development` in `resources/boost/skills/`. It covers how a call runs (config check, login, the `JSESSIONID` cookie, the single retry on a `401`), what every failure gives you, reading documents with your own filter, fields and sort, paging past MKG's row caps, the pitfalls in a host app (field lists, normalisation, published CSV files, the cookie on the default disk, a custom Guzzle client), finding a stalling sync in the request log, the settings, and testing with a Guzzle `MockHandler` instead of an MKG installation.
- Social preview image for the documentation site (`docs/assets/images/social-preview.png`), set as the default Open Graph and Twitter card image in `docs/_config.yml`.

## [1.2.0] - 2026-09-18

The package now has the same shape as the other darvis packages: a documentation
site, a Laravel Boost guideline, and Pint, Larastan and the shared CI matrix
behind it. Nothing changes in the public API.

### Added

- Laravel Boost guideline in `resources/boost/guidelines/core.blade.php`, so host apps that run Boost get the package's rules (derived URLs, what a `401` and a `403` mean, the row caps and paging, the CSV field metadata) in their AI context.
- Pint (`composer lint`, `composer format`) and Larastan level 8 (`composer analyse`) as development tooling, and the shared CI workflow that runs the suite on PHP 8.2 to 8.4 with Laravel 11, 12 and 13 on the lowest and the latest dependencies.
- `SECURITY.md`, `CONTRIBUTING.md`, issue templates and a Dependabot schedule for the dev tooling.

### Changed

- The documentation moved to a GitHub Pages site at https://arviddejong.github.io/mkg-client/, built from `docs/`: installation, usage, a service reference generated from the source, verification, troubleshooting and the FAQ. The README now holds the quick start and links there; the MKG API answers live in the FAQ page and in `llms.txt`.
- The keys in `config/mkg.php` are in alphabetical order. Values and environment variables are unchanged; republish the config only if you want the new order.
- Pest 4 and PHPUnit 12 are allowed for the test suite. Nothing changes for host apps.

## [1.1.2] - 2026-09-01

### Changed

- Attribution made explicit. The author credit sat on the last line of the README,
  outside the part an assistant reads, and said "maintained by" rather than who
  built it. It now appears near the top as "Developed by Arvid de Jong — ARVID.NL",
  with the Darvis vendor namespace named as publisher, plus website, GitHub and
  LinkedIn links.
- One contact address throughout. The author entry used `info@arvid.nl` while the
  business contact is `arvid@darvis.nl`; two addresses read as uncertainty, so the
  package now uses `arvid@darvis.nl` everywhere, including `support.email`.
- Stated availability for AI and software work, so the enquiry route is visible
  rather than implied.

### Added

- `CITATION.cff`, which GitHub renders as a "Cite this repository" block and which
  is machine-readable. It deliberately carries no `version` or `date-released`:
  those would go stale at every release, and a wrong fact is worse than a missing
  one.

## [1.1.1] - 2026-09-01

### Changed

- Package metadata and README rewritten so the project is identifiable without
  prior knowledge: what MKG is, that this is PHP, how it is installed, and under
  which licence. The Composer description, keywords and support links were
  expanded, and the GitHub repository description, homepage and topics were filled
  in (they were empty).
- Added a frequently-asked-questions section answering the MKG API questions that
  are hard to find elsewhere: the base URL, how the form login works, what a `403`
  and a `401` each mean, the row caps and how to page. Every answer was verified
  against a live MKG installation: omitting `NumRows` returns 100 rows, and
  `NumRows=2000` silently returns 1000.

## [1.1.0] - 2026-09-01

### Added

- `mkg.host` (`MKG_HOST`) and `mkg.client_path` (`MKG_CLIENT_PATH`): the client now
  builds both URLs itself, so the API paths no longer live in every consumer's
  environment file. `mkg.url_auth` and `mkg.url_prod` still override the derived
  values for installations that deviate from the standard layout.
- `MkgHttpException`, thrown for client errors instead of Guzzle's bare
  `ClientException`. It explains that a `403` with an HTML body means the base URL
  path is wrong rather than a permission or session problem, and it surfaces the
  JSON error text MKG returns for other statuses. It extends `ClientException`, so
  existing catch blocks keep working.
- Optional PSR-3 request logging (`MKG_LOG_REQUESTS`, `MKG_SLOW_REQUEST_SECONDS`).
  MKG traffic uses plain Guzzle and is therefore invisible to profilers that hook
  Laravel's HTTP client; each call can now be logged with method, path, status and
  duration. A call slower than the threshold is warned about even when logging is
  off. In Laravel the logger is injected automatically.
- Troubleshooting documentation covering the difference between `401` and `403`,
  and how to trace a stalling sync.

### Fixed

- Configuration and documentation examples pointed at `https://your-mkg-host/restapi`,
  a URL shape that exists on no MKG installation. Replaced with the real layout.

### Notes

- Only `401` triggers re-authentication, and that is deliberate. A `403` comes from
  Tomcat and means the URL is wrong; retrying it doubles the traffic against the
  customer's ERP and fails anyway. Covered by a test so it stays that way.

## [1.0.3] - 2026-06-30

### Fixed

- Pass explicit `enclosure` and `escape` arguments to `fgetcsv` (PHP 8.4 deprecation).

## [1.0.2] - 2026-06-30

### Added

- `SkipRows` pagination and `Sort` passthrough on order list queries
  (`listHeaders`, `listRows`, `listRowParameters`). MKG caps a result set at 1000
  rows per call, so larger sets must be paged or rows go missing without an error.

## [1.0.1] - 2026-06-25

### Changed

- Services build their queries through `listDocument`.
- Maintainer information added to the README.

## [1.0.0] - 2026-03-19

### Added

- Package scaffold for MKG client services.
- Laravel service provider with auto-discovery.
- Publishable config (`mkg-config`) and CSV metadata (`mkg-csv`) tags.
- Combined publish tag (`mkg-client`).
- MKG services for articles, debtors, contact persons, orders, addresses, relations, and users.
- Metadata CSV fallback strategy with app-level override support.
- README documentation with installation, configuration, usage, and troubleshooting.
- Testbench/phpunit test scaffold.

### Changed

- Replaced direct `env()` usage in core service logic with `config()` access.
- Added runtime validation for required MKG configuration values.
- Made timeout, SSL verification, and cookie storage path configurable.
- Clarified Laravel integration via optional `illuminate/support` suggestion in Composer metadata.
- Introduced `RelationsService` as the correct class name.
- Corrected `AddressesService` method parameter name from `$adrsessNumber` to `$addressNumber` (named argument calls should be updated).
