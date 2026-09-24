# darvis/mkg-client

[![Latest Version](https://img.shields.io/packagist/v/darvis/mkg-client.svg)](https://packagist.org/packages/darvis/mkg-client)
[![Tests](https://github.com/ArvidDeJong/mkg-client/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/mkg-client/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![License](https://img.shields.io/packagist/l/darvis/mkg-client.svg)](LICENSE)

A PHP client that reads data from the REST API of [MKG Software](https://www.mkg.eu), the
Dutch ERP system. It handles the form login and the `JSESSIONID` session for you, and
gives you one service class per MKG document instead of hand-built URLs. It works in
plain PHP and registers itself in Laravel. An independent open-source package, not
affiliated with MKG Software.

## Features

- **Login and session handled for you**: the form login, the `JSESSIONID` cookie, the `X-CustomerID` header, and one automatic new login on a `401`
- **Derived URLs**: set the host and the client builds the REST base and the login URL
- **A service per document**: `arti`, `debi`, `cprs`, `vorh`, `vorr`, `vopa`, `adrs`, `rela` and `gebr`, read only
- **Typed rows**: MKG's field metadata turns integers, amounts, booleans and dates into PHP values; quantities (`aantal`) and a few other types stay as MKG sent them
- **Safe lookups**: the finders quote and escape their value, and primary keys are URL-encoded
- **Errors you can act on**: a `403` is explained as a wrong base URL, and a redirect or a login page throws instead of looking like "no rows"
- **Request logging**: every call with its duration, and a warning for a slow call

## Requirements

- PHP 8.2 or higher
- An MKG installation with the API set up, an MKG Exchange license and an API key
- Laravel 11, 12 or 13, only for the Laravel integration

## Installation

```bash
composer require darvis/mkg-client
```

```dotenv
MKG_HOST=your-mkg-host
MKG_CUSTOMER=your-api-key
MKG_USERNAME=your-api-username
MKG_PASSWORD=your-api-password
```

The client builds the REST base and the login URL from the host. See
[Installation & configuration](https://arviddejong.github.io/mkg-client/installation.html)
for what to ask MKG for, every setting, and a command that checks the connection.

## Quick start

```php
use Darvis\MkgClient\Exceptions\MkgHttpException;
use Darvis\MkgClient\Services\DebtorsService;
use Darvis\MkgClient\Services\OrdersService;
use GuzzleHttp\Exception\GuzzleException;

try {
    $debtor = app(DebtorsService::class)->findDebtorRowsByDebtorNumber(10001)[0] ?? null;

    $lines = app(OrdersService::class)->findOrderLineRowsByOrderNumber(
        'VK2606096',
        ['vorh_num', 'vorr_num', 'arti_code'],
    );
} catch (MkgHttpException $e) {
    // MKG answered with a 4xx, a redirect or something that is not JSON.
    report($e);
} catch (GuzzleException $e) {
    // A timeout, a connection error, a 5xx or a failed login.
    report($e);
}
```

The first call logs in and stores the session cookie; an empty array means MKG answered
and found nothing. Pass a field list for orders: the default is every field in the
package's metadata, 355 for order lines.
MKG returns at most 1000 rows per call, and 100 without `numRows`.

## Documentation

Full documentation: **https://arviddejong.github.io/mkg-client/**

| Page | |
| --- | --- |
| [Installation & configuration](https://arviddejong.github.io/mkg-client/installation.html) | What to ask MKG for, the steps, every setting, a check that it works |
| [Usage](https://arviddejong.github.io/mkg-client/usage.html) | A complete example, rows versus the raw response, filters, paging, exceptions, plain PHP |
| [Service reference](https://arviddejong.github.io/mkg-client/services.html) | Every service and the signature of every public method |
| [Testing](https://arviddejong.github.io/mkg-client/testing.html) | Test your code with a Guzzle `MockHandler`, without an MKG installation |
| [Verification](https://arviddejong.github.io/mkg-client/verification.html) | Check the URL and the credentials with curl |
| [Troubleshooting](https://arviddejong.github.io/mkg-client/troubleshooting.html) | Every exception message and log line, with cause and fix |
| [FAQ](https://arviddejong.github.io/mkg-client/faq.html) | Short answers about the package and the MKG API |

## Laravel Boost

The package ships [Laravel Boost](https://laravel.com/docs/boost) resources: a guideline
and a `mkg-client-development` skill. Run `php artisan boost:install`, or
`php artisan boost:update --discover` in a project that already uses Boost.

## Testing

```bash
composer test      # Pest
composer lint      # Pint, check only (composer format fixes)
composer analyse   # Larastan, level 8
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Support the package

If darvis/mkg-client saves you time, a star on [GitHub](https://github.com/ArvidDeJong/mkg-client) or a favourite on [Packagist](https://packagist.org/packages/darvis/mkg-client) helps other developers find it.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

Found a security problem? Report it privately, see [SECURITY.md](SECURITY.md).

## License

MIT, see [LICENSE](LICENSE).
