---
title: Usage
description: "Use darvis/mkg-client from Laravel or plain PHP: config providers, filters, sorting, paging within MKG's row caps, and request logging to find where a sync stalls."
nav_order: 3
---

# Usage

## Laravel

Resolve a service from the container; the config, the cookie store and the logger are wired for you:

```php
use Darvis\MkgClient\Services\DebtorsService;

$service = app(DebtorsService::class);
$rows = $service->findDebtorRowsByNumberNameOrEmail('10001');
```

## Plain PHP

Pass an `ArrayConfigProvider` and, optionally, a PSR-3 logger. Without a config provider the client reads Laravel's config when Laravel is present and falls back to environment variables.

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
    'mkg.cookie_storage_path' => __DIR__.'/var/mkg-cookie.txt',
]);

$service = new DebtorsService(config: $config, logger: $logger);
$response = $service->list(numRows: 5);
```

The cookie is a live ERP session. The file is created for the owner only (`0600`, a new directory with `0700`) and replaced in one step; give it a directory that only your application user can write to, not a shared one such as `/tmp`.

The constructor also accepts your own Guzzle `Client` and a `CookieStoreInterface` implementation, in that order: `new DebtorsService($client, $config, $cookieStore, $logger)`.

## Rows or the raw response

Every service has two kinds of finders. The `find…Rows…` methods return the flat rows, normalised with the CSV metadata. The plain `find…` and `list…` methods return MKG's raw response, with the rows under `response.ResultData[0].{document}`; use the service's `extract…Rows()` method to get them out.

```php
use Darvis\MkgClient\Services\ArticleService;
use Darvis\MkgClient\Services\OrdersService;

$articles = app(ArticleService::class)->findArticleRowsByCodeOrName('PROFIEL');

$orders = app(OrdersService::class);
$headers = $orders->findHeaderRowsByOrderNumber('500123');
$lines = $orders->findOrderLineRowsByOrderNumber('500123');
$params = $orders->findOrderRowParameterRowsByOrderNumber('500123');
```

## Filters, fields and sorting

The `list` methods pass MKG's own query options through: `fieldList` (the fields to return, defaults to the document's default list), `filter` (MKG filter syntax, for example `vorh_num = VK2606096`), `numRows` and `sort` (prefix a field with `-` for descending). Field names stay exactly as MKG spells them.

```php
$orders->listHeaders(
    fieldList: ['vorh_num', 'vorh_dat_order', 'debi_num'],
    filter: 'debi_num = 10001',
    sort: '-vorh_dat_order',
    numRows: 50,
);
```

The number lookups (`findHeaderByOrderNumber()`, `findByDebtorNumber()`, `findByRelationNumber()` and the like) build their own filter. A plain number goes in as it is (`debi_num = 10001`). Any other value, such as the order number `VK2606096`, is compared as a quoted text with the backslash and the quote escaped (`vorh_num = "VK2606096"`), so the value can never become part of the filter expression; an empty value throws an `InvalidArgumentException` before a request is sent. The text lookups escape the same way and refuse control characters. The `get…ByPrimaryKey()` methods URL-encode every part of the key and refuse an empty part, `.` and `..`.

A filter you write yourself in the `filter` argument is sent as you wrote it. Never build one from the input of a visitor without validating it first.

## Errors

A `4xx` from MKG, a redirect and a `2xx` that is not JSON all throw `Darvis\MkgClient\Exceptions\MkgHttpException`, which extends Guzzle's `ClientException`. An empty array therefore always means that MKG answered and found nothing. See [Troubleshooting](troubleshooting.md).

## Paging

MKG caps a result set at 1000 rows per call and returns 100 when `NumRows` is omitted, without any error or warning when more rows exist. Anything larger has to be paged with `skipRows` as the offset, and with a `sort` so the order is stable across pages. `SkipRows` works even though MKG's own documentation does not list it. The order methods `listHeaders()`, `listRows()` and `listRowParameters()` take `skipRows`; for the other documents, narrow the result with a filter.

```php
$page = 0;
do {
    $rows = $orders->extractOrderLineRows($orders->listRows(
        filter: 'vorh_num = VK2606096',
        sort: 'vorr_num',
        numRows: 1000,
        skipRows: $page * 1000,
    ));
    // process $rows
    $page++;
} while (count($rows) === 1000);
```

## Request logging

MKG traffic uses plain Guzzle, so profilers that hook Laravel's HTTP client (such as Debugbar's `http_client` collector) report zero requests and a long stretch of unaccounted time. Switch on request logging to see each call:

```dotenv
MKG_LOG_REQUESTS=true
MKG_SLOW_REQUEST_SECONDS=10
```

```
MKG request completed. {"method":"GET","path":"/mkg/web/v3/MKG/Documents/vorr","status":200,"seconds":0.512}
```

A call slower than `MKG_SLOW_REQUEST_SECONDS` is logged as a warning even when `MKG_LOG_REQUESTS` is off, so a production stall still leaves a trace. In Laravel the logger is injected automatically; in plain PHP, pass any PSR-3 logger to the constructor. A stall is rarely one slow call; it is usually dozens of ordinary ones in a row, which only the sum reveals.

## Available services

All services live under `Darvis\MkgClient\Services` and extend `Darvis\MkgClient\BaseMkgService`: `ArticleService` (arti), `DebtorsService` (debi), `ContactpersonService` (cprs), `OrdersService` (vorh, vorr, vopa), `AddressesService` (adrs), `RelationsService` (rela) and `UserService` (gebr). See the [service reference](services.md) for every method.
