# darvis/mkg-client

Framework-agnostic MKG REST API client with optional Laravel integration.

This API client is intended for MKG ERP: https://www.mkg.eu

It provides a typed service layer for common MKG documents:

- `arti` (articles)
- `debi` (debtors)
- `cprs` (contact persons)
- `vorh` (sales order headers)
- `vorr` (sales order lines)
- `vopa` (sales order line parameters)
- `adrs` (addresses)
- `rela` (relations)
- `gebr` (users)

## Documentation

- [Getting started](docs/GETTING_STARTED.md)
- [Configuration](docs/CONFIGURATION.md)
- [Verification (curl + Postman)](docs/VERIFICATION.md)
- [Usage examples](docs/USAGE.md)
- [Migration notes](docs/MIGRATION.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)
- [API reference](docs/API_REFERENCE.md)

## Requirements

- PHP 8.2+
- Valid MKG API credentials

For Laravel integration, use Laravel 11, 12, or 13.

## Installation

### Packagist

```bash
composer require darvis/mkg-client
```

### Local path repository

```json
{
  "repositories": {
    "darvis-mkg-client": {
      "type": "path",
      "url": "../Packages/mkg-client"
    }
  },
  "require": {
    "darvis/mkg-client": "*"
  }
}
```

Then run:

```bash
composer update darvis/mkg-client
```

### Laravel: publish package assets

Publish config and CSV metadata:

```bash
php artisan vendor:publish --tag=mkg-client
```

Or publish separately:

```bash
php artisan vendor:publish --tag=mkg-config
php artisan vendor:publish --tag=mkg-csv
```

## Quick start

Add to your `.env`:

```dotenv
MKG_URL_AUTH=https://your-mkg-host/restapi/auth
MKG_URL_PROD=https://your-mkg-host/restapi
MKG_CUSTOMER=your-customer-code
MKG_USERNAME=your-api-username
MKG_PASSWORD=your-api-password
```

Laravel usage:

```php
use Darvis\MkgClient\Services\DebtorsService;

$service = app(DebtorsService::class);
$rows = $service->findDebtorRowsByNumberNameOrEmail('10001');
```

Plain PHP usage:

```php
use Darvis\MkgClient\Config\ArrayConfigProvider;
use Darvis\MkgClient\Services\DebtorsService;

$config = new ArrayConfigProvider([
  'mkg.url_auth' => 'https://your-mkg-host/restapi/auth',
  'mkg.url_prod' => 'https://your-mkg-host/restapi',
  'mkg.customer' => 'your-customer-code',
  'mkg.username' => 'your-api-username',
  'mkg.password' => 'your-api-password',
]);

$service = new DebtorsService(config: $config);
$rows = $service->findDebtorRowsByNumberNameOrEmail('10001');
```

## Available services

- `Darvis\MkgClient\Services\ArticleService`
- `Darvis\MkgClient\Services\DebtorsService`
- `Darvis\MkgClient\Services\ContactpersonService`
- `Darvis\MkgClient\Services\OrdersService`
- `Darvis\MkgClient\Services\AddressesService`
- `Darvis\MkgClient\Services\RelationsService`
- `Darvis\MkgClient\Services\UserService`

## Testing

This package uses Pest.

```bash
composer test
```

## Official MKG resources

- [MKG API introduction](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/4767/inleiding-tot-de-mkg-api)
- [MKG Getting Started](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/6598/getting-started)
- [Official MKG API call guide](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/10924/hoe-werken-mkg-api-aanroepen)
- [MKG Postman collection](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/6032/api-postman-collectie)

## Maintainer

This package is maintained by Arvid de Jong (<info@arvid.nl>).

## License

MIT
