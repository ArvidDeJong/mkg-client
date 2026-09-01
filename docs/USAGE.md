# Usage Examples

## Laravel usage

```php
use Darvis\MkgClient\Services\DebtorsService;

$service = app(DebtorsService::class);
$rows = $service->findDebtorRowsByNumberNameOrEmail('10001');
```

## Plain PHP usage

```php
use Darvis\MkgClient\Config\ArrayConfigProvider;
use Darvis\MkgClient\Services\DebtorsService;

$config = new ArrayConfigProvider([
  'mkg.host' => 'your-mkg-host',
  'mkg.customer' => 'your-customer-code',
  'mkg.username' => 'your-api-username',
  'mkg.password' => 'your-api-password',
  'mkg.verify_ssl' => true,
  'mkg.timeout' => 30,
  'mkg.connect_timeout' => 10,
  'mkg.cookie_storage_path' => '/tmp/mkg-cookie.txt',
]);

$service = new DebtorsService(config: $config);
$response = $service->list(numRows: 5);
```

If no config provider is passed, the package checks Laravel config first (when available) and then environment variables.

## Article search

```php
use Darvis\MkgClient\Services\ArticleService;

$service = app(ArticleService::class);
$rows = $service->findArticleRowsByCodeOrName('PROFIEL');
```

## Order search

```php
use Darvis\MkgClient\Services\OrdersService;

$service = app(OrdersService::class);
$headers = $service->findHeaderRowsByOrderNumber('500123');
$lines = $service->findOrderLineRowsByOrderNumber('500123');
$params = $service->findOrderRowParameterRowsByOrderNumber('500123');
```

## Available services

- `Darvis\MkgClient\Services\ArticleService`
- `Darvis\MkgClient\Services\DebtorsService`
- `Darvis\MkgClient\Services\ContactpersonService`
- `Darvis\MkgClient\Services\OrdersService`
- `Darvis\MkgClient\Services\AddressesService`
- `Darvis\MkgClient\Services\RelationsService`
- `Darvis\MkgClient\Services\UserService`

For all method signatures, see [API_REFERENCE.md](API_REFERENCE.md).
