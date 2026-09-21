# darvis/mkg-client

[![Latest Version](https://img.shields.io/packagist/v/darvis/mkg-client.svg)](https://packagist.org/packages/darvis/mkg-client)
[![Tests](https://github.com/ArvidDeJong/mkg-client/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/mkg-client/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-red.svg)](https://laravel.com)
[![Total downloads](https://img.shields.io/packagist/dt/darvis/mkg-client.svg)](https://packagist.org/packages/darvis/mkg-client)
[![License](https://img.shields.io/packagist/l/darvis/mkg-client.svg)](LICENSE)

A PHP client for the REST API of [MKG Software](https://www.mkg.eu), the Dutch ERP
system. It handles the Tomcat form login and the `JSESSIONID` session for you, and
gives you a typed service layer over the MKG documents instead of hand-built URLs.
Framework-agnostic, with a Laravel service provider that registers itself.

An independent open-source package, not affiliated with MKG Software. Developed by
[Arvid de Jong, ARVID.NL](https://arvid.nl) and published under the Darvis vendor
namespace. Available for AI and software work: <arvid@darvis.nl>.

## Features

- **Login and session handled for you**: the form login, the `JSESSIONID` cookie, the `X-CustomerID` header, and one automatic re-login on a `401`
- **Derived URLs**: set the host and the client builds the REST base and the login URL, so nobody types a retired path
- **A typed service per document**: `arti`, `debi`, `cprs`, `vorh`, `vorr`, `vopa`, `adrs`, `rela` and `gebr`
- **Field metadata from CSV**: default field lists and type normalisation per document, overridable per application
- **Clear errors**: a `403` is explained as a wrong base URL, not retried as a session problem
- **Request logging**: every call with its duration, and a warning for slow calls, because Laravel's HTTP client profilers never see plain Guzzle traffic

## Installation

```bash
composer require darvis/mkg-client
```

Add the credentials to `.env`; the client derives the REST base and the login URL
from the host:

```dotenv
MKG_HOST=your-mkg-host
MKG_CUSTOMER=your-customer-code
MKG_USERNAME=your-api-username
MKG_PASSWORD=your-api-password
```

See [Installation & configuration](docs/installation.md) for what MKG needs on its
side, the training environment, every config key and the CSV metadata.

## Usage

```php
use Darvis\MkgClient\Services\DebtorsService;
use Darvis\MkgClient\Services\OrdersService;

// Laravel
$rows = app(DebtorsService::class)->findDebtorRowsByNumberNameOrEmail('10001');
$lines = app(OrdersService::class)->findOrderLineRowsByOrderNumber('500123');
```

```php
use Darvis\MkgClient\Config\ArrayConfigProvider;
use Darvis\MkgClient\Services\DebtorsService;

// Plain PHP
$config = new ArrayConfigProvider([
    'mkg.host' => 'your-mkg-host',
    'mkg.customer' => 'your-customer-code',
    'mkg.username' => 'your-api-username',
    'mkg.password' => 'your-api-password',
]);

$rows = (new DebtorsService(config: $config))->findDebtorRowsByNumberNameOrEmail('10001');
```

A `401` means the session expired and is retried once after a fresh login. A `403`
with an HTML body means the URL path is wrong and never reached the API; it is not
retried. A redirect, or a `2xx` whose body is not JSON (a login page, a proxy error),
throws `MkgHttpException` too, so an empty array always means that MKG answered and
found nothing. MKG caps a result at 1000 rows and returns 100 without `NumRows`, so
page larger sets. See [Troubleshooting](docs/troubleshooting.md).

The lookups quote and escape their value and URL-encode primary keys. A `filter`
string you write yourself is sent as it is: never build one from visitor input. In
Laravel the session cookie is written to the default filesystem disk, which must not
be public; in plain PHP the cookie file is created for the owner only.

## Documentation

Full documentation: **https://arviddejong.github.io/mkg-client/**

| Topic | |
| --- | --- |
| [Installation & configuration](docs/installation.md) | What MKG needs, environment variables, the derived URLs, every config key, CSV metadata |
| [Usage](docs/usage.md) | Laravel and plain PHP, rows versus raw response, filters, paging, request logging |
| [Service reference](docs/services.md) | Every service and its public methods |
| [Verification](docs/verification.md) | Check connectivity and credentials with curl or Postman |
| [Troubleshooting](docs/troubleshooting.md) | 401 versus 403, stalls, missing rows and fields, config, TLS |

Or start at the [documentation index](docs/README.md), or read the [FAQ](https://arviddejong.github.io/mkg-client/faq.html) with the MKG API answers that are hard to find elsewhere.

## Laravel Boost

The package ships [Laravel Boost](https://laravel.com/docs/boost) resources: a guideline
and a `mkg-client-development` skill. Run `php artisan boost:install`, or
`php artisan boost:update --discover` in a project that already uses Boost.

## Development

```bash
composer test      # Pest
composer lint      # Pint, check only (composer format to fix)
composer analyse   # Larastan, level 8
```

GitHub Actions runs the tests on PHP 8.2 to 8.4 against Laravel 11, 12 and 13, with both
the lowest and the latest allowed dependencies. See the [changelog](CHANGELOG.md) for
release notes.

## Contributing and security

See [CONTRIBUTING.md](CONTRIBUTING.md). Found a security problem? Please report it privately, see [SECURITY.md](SECURITY.md).

## Author

**Arvid de Jong** of **[ARVID.NL](https://arvid.nl)**, published under the Darvis vendor
namespace ([darvis.nl](https://darvis.nl)).

- Email: <arvid@darvis.nl>
- Website: <https://arvid.nl>
- GitHub: <https://github.com/ArvidDeJong>
- LinkedIn: <https://www.linkedin.com/in/arviddejong/?locale=nl>

Arvid de Jong builds AI-assisted tooling and custom software for companies, including
ERP integrations such as this one. For an enquiry about a project for your own company,
email <arvid@darvis.nl>.

## License

MIT, see [LICENSE](LICENSE).
