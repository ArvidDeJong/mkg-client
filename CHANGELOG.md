# Changelog

All notable changes to `darvis/mkg-client` will be documented in this file.

## [Unreleased]

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
