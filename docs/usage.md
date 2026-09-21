---
title: "Usage"
description: "A complete Laravel example for darvis/mkg-client, then rows versus the raw MKG response, field lists, filters, paging past 1000 rows, exceptions and plain PHP."
nav_order: 3
---

# Usage

## A complete example: a debtor and its latest orders

This Artisan command looks up one debtor and lists its ten latest sales orders. It shows the three things you do in every integration: a finder, a list call with your own fields and filter, and catching the exceptions.

File: `app/Console/Commands/ShowDebtorOrders.php`

```php
<?php

namespace App\Console\Commands;

use Darvis\MkgClient\Exceptions\MkgHttpException;
use Darvis\MkgClient\Services\DebtorsService;
use Darvis\MkgClient\Services\OrdersService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

class ShowDebtorOrders extends Command
{
    protected $signature = 'mkg:debtor-orders {debtor : The MKG debtor number, digits only}';

    protected $description = 'Show a debtor and its ten latest sales orders from MKG';

    public function handle(DebtorsService $debtors, OrdersService $orders): int
    {
        $debtorNumber = (string) $this->argument('debtor');

        // The value ends up in a filter we write ourselves, so check it first.
        if (! ctype_digit($debtorNumber)) {
            $this->error('The debtor number must consist of digits.');

            return self::FAILURE;
        }

        try {
            $debtor = $debtors->findDebtorRowsByDebtorNumber($debtorNumber)[0] ?? null;

            if ($debtor === null) {
                $this->warn('MKG has no debtor '.$debtorNumber.'.');

                return self::SUCCESS;
            }

            $headers = $orders->extractHeaderRows($orders->listHeaders(
                fieldList: ['vorh_num', 'vorh_dat_order', 'debi_num'],
                filter: 'debi_num = '.$debtorNumber,
                numRows: 10,
                sort: '-vorh_dat_order',
            ));
        } catch (MkgHttpException $e) {
            // MKG answered, but not with something usable: the message says what to fix.
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (GuzzleException $e) {
            // A timeout, a connection error, a 5xx, or a failed login.
            $this->error('MKG could not be reached: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info($debtor['debi_num'].' '.$debtor['debi_naam']);
        $this->table(
            ['Order', 'Date', 'Debtor'],
            array_map(fn (array $row): array => [$row['vorh_num'], $row['vorh_dat_order'], $row['debi_num']], $headers),
        );

        return self::SUCCESS;
    }
}
```

Run it with `php artisan mkg:debtor-orders 10001`. The first call logs in and stores the session cookie; the calls after it reuse the cookie. Laravel creates the two services when it calls `handle()`, not when it builds the command, so a machine without `MKG_*` values can still run `php artisan list`.

Document and field names stay exactly as MKG spells them: `debi` is the debtor document, `vorh` the sales order header, `vorh_dat_order` the order date.

## Rows or the raw response

Every service has two kinds of methods.

| Method name | Returns |
| --- | --- |
| `find…Rows…()`, `extract…Rows()` | A flat list of rows, with values converted to PHP types |
| `list…()`, `find…()` without `Rows`, `get…ByPrimaryKey()` | MKG's raw response as an array, with the rows under `response.ResultData[0].{document}`, unconverted |

Pass a raw response to the `extract…Rows()` method of the same service to get the rows out, as the example does with `extractHeaderRows()`. A single row that MKG returns as an object is wrapped in a list, so you always get a list.

```php
use Darvis\MkgClient\Services\ArticleService;
use Darvis\MkgClient\Services\OrdersService;

$articles = app(ArticleService::class)->findArticleRowsByCodeOrName('PROFIEL');

$orders = app(OrdersService::class);
$headers = $orders->findHeaderRowsByOrderNumber('VK2606096');
$lines = $orders->findOrderLineRowsByOrderNumber('VK2606096');
$parameters = $orders->findOrderRowParameterRowsByOrderNumber('VK2606096');
```

The combined searches send more than one request. `findDebtorRowsByNumberNameOrEmail()` and `findContactpersonRowsByNumberNameOrEmail()` search on number (two requests) when the input is all digits, on email when it contains `@`, and on name otherwise. `findArticleRowsByCodeOrName()` and `findUserRowsByCodeOrName()` always send two requests and merge the result without duplicates. The name, email and combined searches return at most `numRows` rows, 25 by default.

## Which values are converted

The conversion follows the type of the field in the CSV file of the document, not the name of the field.

| Type in the CSV | PHP value |
| --- | --- |
| `integer` | `int` |
| `decimal`, `percentage`, `bedrag` (amount) | `float`, also from `1.234,56` |
| `logical` | `bool`, also from `WAAR`, `ONWAAR`, `ja`, `nee` |
| `datum` | string `Y-m-d`, from `d-m-Y` |
| `character`, `omschrijving`, `memo`, `e-mail`, `naw` | `string` |
| every other type, among them `aantal` (quantity), `tijdstip`, `week` and `telefoonnummer` | unchanged, as MKG sent it |

Three consequences:

- `debi_num` and `vorh_num` are `character` fields in MKG. They come back as strings: compare with `'10001'`, not `10001`.
- A quantity such as `vorr_order_aantal` has the type `aantal` and is not converted. Convert it yourself when you calculate with it.
- A value that does not fit its type (text in an `integer` field) stays as it was. A field that is not in the CSV stays as it was too.

## Ask only for the fields you need

Every `list…()` and `find…()` method takes a `fieldList`. Without it the service sends its default list:

| Service | Default field list |
| --- | --- |
| `ArticleService`, `DebtorsService`, `ContactpersonService`, `UserService` | A short built-in list (9 fields for debtors) |
| `OrdersService` | Every field in the CSV: 158 for `vorh`, 355 for `vorr`, 16 for `vopa` |
| `AddressesService`, `RelationsService` | Every field in the CSV: 37 for `adrs`, 113 for `rela` |

A list of hundreds of fields makes a long URL and a slow call, and it includes calculated fields. Pass your own list for orders, addresses and relations:

```php
$lines = $orders->findOrderLineRowsByOrderNumber('VK2606096', ['vorh_num', 'vorr_num', 'arti_code']);
```

`getDefaultOrderFieldList(databaseOnly: true)`, and the same method on the other services, returns only the fields that are stored in the MKG database (89 for `vorh`).

## Filter and sort

The `list…()` methods pass MKG's own query options through: `fieldList`, `filter` (MKG's filter syntax, for example `vorh_num = VK2606096`), `numRows` and `sort`. `sort` takes a field name, with `-` in front for descending. `AddressesService::list()` and `RelationsService::list()` have no `sort` argument.

```php
$response = $orders->listHeaders(
    fieldList: ['vorh_num', 'vorh_dat_order', 'debi_num'],
    filter: 'debi_num = 10001',
    numRows: 50,
    sort: '-vorh_dat_order',
);

$rows = $orders->extractHeaderRows($response);
```

**A filter you write yourself is sent as you wrote it.** Never put the input of a visitor in it without validating it first, as the example at the top does with `ctype_digit()`.

The finders build their own filter and make the value safe:

- A plain number (`debi_num = 10001`) and a plain key of letters and digits with at least one digit (`vorh_num = VK2606096`) go in as they are.
- Every other value is compared as a quoted text, with the backslash and the quote escaped (`vorh_num = "VK-2606096"`), so it cannot become part of the filter expression.
- An empty value in a lookup by number, and a control character (a line break, a tab, a NUL byte) in a text search, throw an `InvalidArgumentException` before a request is sent. The searches on name, email, article code and user code, and the combined searches, return `[]` for an empty string without sending a request.
- The `get…ByPrimaryKey()` methods URL-encode every part of the key and refuse an empty part, `.` and `..`.

## Read more than 1000 rows

MKG returns 100 rows when `NumRows` is left out and never more than 1000 per call, without an error or a warning when more exist. The client sends what you pass; it does not page by itself. Page with `skipRows` as the offset, and pass a `sort` so the order is the same on every page. `listHeaders()`, `listRows()` and `listRowParameters()` take `skipRows`; narrow the other documents with a filter.

```php
$page = 0;

do {
    $rows = $orders->extractOrderLineRows($orders->listRows(
        fieldList: ['vorh_num', 'vorr_num', 'arti_code'],
        filter: 'vorh_num = VK2606096',
        numRows: 1000,
        sort: 'vorr_num',
        skipRows: $page * 1000,
    ));

    // process $rows

    $page++;
} while (count($rows) === 1000);
```

`numRows: 0` is not sent at all, which gives MKG's default of 100.

## What a call can throw

| Situation | Exception |
| --- | --- |
| A `4xx` from MKG, a second `401` after the new login, a redirect, a `2xx` that is not JSON | `Darvis\MkgClient\Exceptions\MkgHttpException`, which extends Guzzle's `ClientException` |
| A `5xx` from MKG | Guzzle's `ServerException`, not wrapped and not retried |
| A timeout or a connection error | Guzzle's `ConnectException` |
| A `4xx` or `5xx` on the login call itself | Guzzle's own `ClientException` or `ServerException`, not an `MkgHttpException` |
| A missing config value | `RuntimeException` |
| An empty value in a lookup by number, a control character in a text search, an empty key part, `.` or `..` | `InvalidArgumentException`, before any request |

Catch `GuzzleHttp\Exception\GuzzleException` to cover everything a request can throw, and catch `MkgHttpException` first when you want the explained message. An empty array always means that MKG answered and found nothing; stop a sync on an exception instead of treating it as "no rows". The messages are listed in [Troubleshooting](troubleshooting.md).

## Plain PHP

Without Laravel, pass the settings in an `ArrayConfigProvider`. It takes flat keys with a dot (`'mkg.host'`), not a nested array, and it does not fall back to the environment for a key you leave out.

File: `mkg-example.php`

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Darvis\MkgClient\Config\ArrayConfigProvider;
use Darvis\MkgClient\Cookies\FileCookieStore;
use Darvis\MkgClient\Services\DebtorsService;

$config = new ArrayConfigProvider([
    'mkg.host' => 'your-mkg-host',
    'mkg.customer' => 'your-api-key',
    'mkg.username' => 'your-api-username',
    'mkg.password' => 'your-api-password',
    'mkg.cookie_storage_path' => __DIR__.'/var/mkg-cookie.txt',
]);

$debtors = new DebtorsService(config: $config, cookieStore: new FileCookieStore);

print_r($debtors->findDebtorRowsByDebtorNumber(10001));
```

The script logs in, stores the cookie in `var/mkg-cookie.txt` (mode `0600`, replaced in one step) and prints the rows. Give the cookie a directory that only your application user can write to, not a shared one such as `/tmp`.

- The constructor arguments are `client`, `config`, `cookieStore` and `logger`, in that order. Use named arguments.
- Pass `cookieStore: new FileCookieStore` as the example does. Without it the client picks the Laravel store as soon as the class `Illuminate\Support\Facades\Storage` can be loaded, which is also the case in a plain PHP project that has `illuminate/support` installed.
- Nothing is logged until you pass a PSR-3 logger as `logger:`.
- Without a `config` argument the client reads Laravel's config when Laravel is present, and the `MKG_*` environment variable for every value that is not set there.

The same constructor serves an application with more than one MKG installation. Give every installation its own `cookie_storage_path`, or they overwrite each other's session.

## Your own Guzzle client

The first constructor argument takes your own `GuzzleHttp\Client`, which is how [tests](testing.md) replace MKG. A client you pass replaces everything the package configures: `verify_ssl`, `timeout`, `connect_timeout`, `log_requests` and `slow_request_seconds` do nothing, and redirects are followed unless you switch them off. Set these yourself:

```php
use GuzzleHttp\Client;

$client = new Client([
    'allow_redirects' => false,
    'connect_timeout' => 10,
    'http_errors' => true,
    'timeout' => 30,
    'verify' => true,
]);
```

`http_errors` must stay on: the new login after a `401` and `MkgHttpException` both depend on Guzzle throwing.

## See every call in the log

MKG traffic goes out over plain Guzzle, so tools that hook Laravel's HTTP client, such as Debugbar's `http_client` collector, show no requests. Switch on request logging to see each call:

```dotenv
MKG_LOG_REQUESTS=true
MKG_SLOW_REQUEST_SECONDS=10
```

```
MKG request completed. {"method":"GET","path":"/mkg/web/v3/MKG/Documents/vorr","status":200,"seconds":0.512}
```

The line holds the method, the path, the status and the seconds. The query string, and with it the filter, is not logged. Three lines are warnings and appear even when `MKG_LOG_REQUESTS` is off: `MKG request was slow.` for a call that takes `MKG_SLOW_REQUEST_SECONDS` or longer, `MKG request failed.` for a failed call, and `MKG response was not usable.` for a redirect or a body that is not JSON.

In Laravel the container passes the application's logger to a service you resolve with `app()` or type-hint. A sync that stalls is usually many ordinary calls in a row, for example one lookup per order line, so add up the lines before blaming one call.
