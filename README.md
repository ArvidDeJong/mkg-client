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
MKG_HOST=your-mkg-host
MKG_CUSTOMER=your-customer-code
MKG_USERNAME=your-api-username
MKG_PASSWORD=your-api-password
```

The client builds both URLs from the host:

| | |
| --- | --- |
| REST base | `https://{host}/mkg/web/v3/MKG/Documents` |
| Authentication | `https://{host}/mkg/static/auth/j_spring_security_check` |

Keep the paths out of your environment file. They belong to the MKG API version,
not to your environment, and a typo there is answered with a `403` and a Tomcat
HTML error page that looks like a permission problem. The retired `/mkg/rest/v1`
and the plausible-looking `/mkg/rest/v3` both fail that way.

Set `MKG_URL_AUTH` and `MKG_URL_PROD` only for an installation that deviates
from the standard layout; they override the derived values.

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
  'mkg.host' => 'your-mkg-host',
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

## Troubleshooting

### 401 and 403 mean different things

| Response | Cause | What the client does |
| --- | --- | --- |
| `401` with JSON `{"status_code":401,"status_txt":"Not authenticated"}` | The session cookie expired or was never valid. | Drops the cached cookie, logs in again and retries once. |
| `403` with a Tomcat HTML error page | The request never reached the REST API: the base URL path is wrong or retired. | Throws `MkgHttpException` naming the configured base and the expected path. |

A `403` is never a session problem, so re-authenticating on it only doubles the
traffic against the customer's ERP and fails again. Do not add `403` to the retry.

### Seeing where a sync stalls

MKG traffic uses plain Guzzle, so profilers that hook Laravel's HTTP client (such
as Debugbar's `http_client` collector) report zero requests and a long stretch of
unaccounted time. Switch on request logging to see each call:

```dotenv
MKG_LOG_REQUESTS=true
MKG_SLOW_REQUEST_SECONDS=10
```

```
MKG request completed. {"method":"GET","path":"/mkg/web/v3/MKG/Documents/vorr","status":200,"seconds":0.512}
```

A call slower than `MKG_SLOW_REQUEST_SECONDS` is logged as a warning even when
`MKG_LOG_REQUESTS` is off, so a production stall still leaves a trace. In Laravel
the logger is injected automatically; in plain PHP, pass any PSR-3 logger as the
fourth constructor argument.

Note that a stall is rarely one slow call. It is usually dozens of ordinary ones
in a row, which only the sum reveals.

### Paging

`SkipRows` works even though MKG's own documentation does not list it. MKG caps a
result set at 1000 rows per call (100 when `NumRows` is omitted), so anything
larger must be paged or rows go missing without any error.

## Official MKG resources

- [MKG API introduction](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/4767/inleiding-tot-de-mkg-api)
- [MKG Getting Started](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/6598/getting-started)
- [Official MKG API call guide](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/10924/hoe-werken-mkg-api-aanroepen)
- [MKG Postman collection](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/6032/api-postman-collectie)

## Maintainer

This package is maintained by Arvid de Jong (<info@arvid.nl>).

## License

MIT
