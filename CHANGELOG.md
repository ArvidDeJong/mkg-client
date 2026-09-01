# Changelog

All notable changes to `darvis/mkg-client` will be documented in this file.

## [Unreleased]

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
