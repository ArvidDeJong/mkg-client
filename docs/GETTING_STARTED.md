# Getting Started

This guide helps you install and run `darvis/mkg-client` quickly.

## Requirements

- PHP 8.2+
- Valid MKG API credentials
- Laravel 11, 12, or 13 (only when using Laravel integration)

## Install

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

## Laravel setup

Publish config and CSV metadata:

```bash
php artisan vendor:publish --tag=mkg-client
```

Or publish separately:

```bash
php artisan vendor:publish --tag=mkg-config
php artisan vendor:publish --tag=mkg-csv
```

## Minimal environment variables

Add to `.env`:

```dotenv
MKG_URL_AUTH=https://your-mkg-host/restapi/auth
MKG_URL_PROD=https://your-mkg-host/restapi
MKG_CUSTOMER=your-customer-code
MKG_USERNAME=your-api-username
MKG_PASSWORD=your-api-password
```

Optional:

```dotenv
MKG_VERIFY_SSL=true
MKG_TIMEOUT=30
MKG_CONNECT_TIMEOUT=10
MKG_COOKIE_STORAGE_PATH=mkg/cookie.txt
```

## First call

Laravel example:

```php
use Darvis\MkgClient\Services\DebtorsService;

$service = app(DebtorsService::class);
$rows = $service->findDebtorRowsByNumberNameOrEmail('10001');
```

Plain PHP example:

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

## Next steps

- [Configuration](CONFIGURATION.md)
- [Verification](VERIFICATION.md)
- [Usage examples](USAGE.md)
- [API reference](API_REFERENCE.md)
