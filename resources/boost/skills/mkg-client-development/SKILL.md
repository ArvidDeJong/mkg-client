---
name: mkg-client-development
description: Work with darvis/mkg-client. Use it to read debtors, articles, sales orders and other MKG ERP documents through the typed services, page past MKG's row caps, tell a 401 from a 403, find a stalling sync in the request log, and test MKG code without calling an MKG installation.
---

# darvis/mkg-client development

## When to use this skill

Use this skill when code reads from MKG in an application that has `darvis/mkg-client` installed, when a call ends in a `401`, a `403`, a redirect, an answer that is not JSON or a `RuntimeException` about missing configuration, when rows or fields are missing without an error, when a sync stalls, or when you write tests around code that talks to MKG.

## How a call runs

1. Resolving a service (`app(DebtorsService::class)` or `new DebtorsService(...)`) reads the config and validates it. It does not log in. `mkg.customer`, `mkg.username` and `mkg.password` must be filled, and the login URL must be resolvable from `mkg.url_auth` or `mkg.host`; otherwise the constructor throws a `RuntimeException`.
2. The first request looks for a cached cookie at `mkg.cookie_storage_path` (default `mkg/cookie.txt`). Only a value that starts with `JSESSIONID=` is accepted. Without one the client posts `j_username` and `j_password` as a form, with the `X-CustomerID` header, to `https://{host}/{client_path}/static/auth/j_spring_security_check` and stores the first cookie of the `Set-Cookie` header.
3. The request goes to `https://{host}/{client_path}/web/v3/MKG/Documents/{document}` with the headers `X-CustomerID`, `Cookie` and `Accept: application/json`. List options travel as the query parameters `FieldList`, `Filter`, `NumRows`, `Sort` and `SkipRows`.
4. On a `401` the cached cookie is deleted, the client logs in again and repeats the request once. Every other `4xx`, and a second `401`, is thrown as `Darvis\MkgClient\Exceptions\MkgHttpException`, which extends Guzzle's `ClientException`.
5. The JSON body is decoded to an array. A `3xx` and a `2xx` body that is not JSON throw `MkgHttpException`; they are never returned as an empty result. The `find…Rows…` and `extract…Rows()` methods take the rows from `response.ResultData[0].{document}` and normalise them with the CSV metadata; a single row that MKG returns as an object is wrapped in a list.

| Situation | What you get | Log line (needs a logger) |
| --- | --- | --- |
| `customer`, `username` or `password` empty | `RuntimeException` from the constructor: `Missing MKG configuration values: mkg.customer, mkg.password` | none |
| No `host` and no `url_auth` | `RuntimeException` from the constructor: `Missing MKG configuration values: set mkg.host (for example "saas1.mkg.eu"), or set mkg.url_auth explicitly.` | none |
| `401` once | Handled: new login, one retry, the result of the retry | warning `MKG request failed.` for the first attempt |
| `401` again after the retry (wrong credentials look like this too) | `MkgHttpException`: `MKG returned 401: Not authenticated. Original error: …` | warning `MKG request failed.` twice |
| `403` with an HTML body | `MkgHttpException`: `MKG returned 403 with an HTML error page, which means the request never reached the REST API: the base URL path is wrong or no longer exists. Configured base: … Expected the path to end in "/web/v3/MKG/Documents" …`. No retry, the cookie is kept | warning `MKG request failed.` |
| Other `4xx` with `status_txt` or `message` in a JSON body | `MkgHttpException`: `MKG returned 400: <text>. Original error: …` | warning `MKG request failed.` |
| Other `4xx` without such a body | `MkgHttpException` with Guzzle's original message | warning `MKG request failed.` |
| `5xx` | Guzzle's `ServerException`, not wrapped and not retried | warning `MKG request failed.` |
| Timeout or connection error | Guzzle's `ConnectException`, not wrapped and not retried | warning `MKG request failed.` with `status` null |
| `4xx` or `5xx` on the login call itself | Guzzle's own exception, not wrapped in `MkgHttpException` | warning `MKG request failed.` |
| A `3xx` (redirects are never followed) | `MkgHttpException`: `MKG answered 302, a redirect, where a JSON document was expected. …` with the configured base. The `Location` header is not in the message | warning `MKG response was not usable.` |
| A `2xx` whose body is not JSON (a login page, a proxy error, an empty body on a read) | `MkgHttpException`: `MKG answered 200 with a body that is not valid JSON (Content-Type: …, … bytes), so it is not an empty result. …`. The body is not in the message; `$e->getResponse()` has it | warning `MKG response was not usable.` |
| A `2xx` with `[]`, `{}` or an envelope without rows | The decoded array, and `[]` from the row methods | debug `MKG request completed.` only with `log_requests` on |
| Empty value in a number lookup, a control character in a text lookup, an empty, `.` or `..` part of a primary key | `InvalidArgumentException` before any request | none |
| Empty search string in a `find…` method that trims its input | `[]` without a request | none |
| Slow call | The normal result | warning `MKG request was slow.` |

Catch `GuzzleHttp\Exception\GuzzleException` to cover everything a call can throw; catch `MkgHttpException` first when you want the explained message.

## Reading documents

All services live in `Darvis\MkgClient\Services`: `ArticleService` (`arti`), `DebtorsService` (`debi`), `ContactpersonService` (`cprs`), `OrdersService` (`vorh`, `vorr`, `vopa`), `AddressesService` (`adrs`), `RelationsService` (`rela`) and `UserService` (`gebr`).

- `find…Rows…()` and `extract…Rows()` return a flat list of rows, normalised.
- `list…()`, the plain `find…()` and `get…ByPrimaryKey()` return MKG's raw response array.

```php
use Darvis\MkgClient\Services\ArticleService;
use Darvis\MkgClient\Services\DebtorsService;
use Darvis\MkgClient\Services\OrdersService;

// Digits: debtor number and relation number, merged without duplicates (two requests).
// Contains "@": debi_email contains. Anything else: debi_naam contains.
$debtors = app(DebtorsService::class)->findDebtorRowsByNumberNameOrEmail('10001');

// Exact arti_code plus "arti_oms_1 contains", merged on arti_code (two requests).
$articles = app(ArticleService::class)->findArticleRowsByCodeOrName('PROFIEL', numRows: 50);

$orders = app(OrdersService::class);
$header = $orders->findHeaderRowsByOrderNumber('VK2606096')[0] ?? null;
$lines = $orders->findOrderLineRowsByOrderNumber('VK2606096');
$parameters = $orders->findOrderRowParameterRowsByOrderNumber('VK2606096');
```

The name, email and combined search methods default to `numRows: 25`. The lookups by number or code ask for one row.

### Your own filter, fields and sort

```php
$response = $orders->listHeaders(
    fieldList: ['vorh_num', 'vorh_dat_order', 'debi_num'],
    filter: 'debi_num = 10001',
    numRows: 50,
    sort: '-vorh_dat_order',
);

$rows = $orders->extractHeaderRows($response);
```

Field and document names stay as MKG spells them. `sort` takes a field name, with `-` in front for descending. `AddressesService::list()` and `RelationsService::list()` have no `sort` argument, and only the three `OrdersService` list methods have `skipRows`.

### Paging

MKG returns 100 rows when `NumRows` is left out and never more than 1000 per call, without an error when more exist. The client sends what you pass and does not page or warn by itself.

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

`skipRows: 0` is sent as `SkipRows=0`. `numRows: 0` is not sent at all, which gives MKG's default of 100. The documents without `skipRows` have to be narrowed with a filter.

## Pitfalls

- **Resolving a service throws when MKG is not configured.** The constructor validates the config, so constructor injection in a controller, command or job fails with the `RuntimeException` above on a machine without the `MKG_*` values, CI included. Resolve the service where you use it, or set the values in the test.
- **Set `MKG_HOST`, not the URLs.** The host may carry a port; a scheme is stripped and the client always builds `https://`. `MKG_CLIENT_PATH` is `mkg`, or `mkgoefenclient` for a training environment. When you do override, set both `MKG_URL_AUTH` and `MKG_URL_PROD`: with only `url_prod` and no host the constructor throws. `/mkg/rest/v1` and `/mkg/rest/v3` end in the `403` above.
- **A `403` is not a permission problem and not an expired session.** Don't add a retry or a fresh login around it. It doubles the traffic against the customer's ERP and fails again.
- **An empty result means MKG answered and found nothing, within the row cap.** A redirect or a non-JSON `2xx` throws `MkgHttpException` (up to 1.2.1 it came back as `[]`); catch it around a sync and stop the run, don't turn it into an empty list. Rows beyond 100 (or 1000) are still cut off silently.
- **The default field list of orders, addresses and relations is every field in the CSV.** For `vorh` that is more than 150 fields in the query string, calculated fields included. Pass a `fieldList`, or use `getDefaultOrderFieldList(databaseOnly: true)` and its siblings to keep to database fields.
- **A default field that is missing from the CSV is dropped silently** (`ArticleService`, `DebtorsService`, `ContactpersonService`, `UserService` filter their default list against it). A `fieldList` you pass yourself is sent as it is; a field in it that the CSV does not know comes back without normalisation.
- **Normalisation follows the CSV type, not the field name.** `integer` becomes `int`; `decimal`, `percentage` and `bedrag` become `float` (also from `1.234,56`); `logical` becomes `bool` (also from `WAAR`, `ONWAAR`, `ja`, `nee`); `datum` becomes `Y-m-d` (from `d-m-Y`); text types become `string`. Every other type, `aantal` included, stays as MKG sent it. `debi_num` and `vorh_num` are `character` in MKG, so they are strings: compare with `'10001'`, not `10001`. The raw `list…()` and `find…()` responses are not normalised at all.
- **Published CSV files win and go stale.** `php artisan vendor:publish --tag=mkg-csv` copies them to `storage/mkg/`, and `storage/mkg/{document}.csv` is then read instead of the package file, also after a package update. The metadata is cached in a static property per service class, so a queue worker or Octane needs a restart to see a changed CSV.
- **The lookups make their value safe; a `filter` string you write yourself is sent as it is.** `findHeaderByOrderNumber()`, `findByDebtorNumber()` and the like send a plain number (`debi_num = 10001`) or a plain key of letters and digits (`vorh_num = VK2606096`) unquoted, and every other value as a quoted text with the backslash and the quote escaped (`vorh_num = "VK-2606096"`). The text lookups escape the same way. The `get…ByPrimaryKey()` methods URL-encode every part of the key; a whole key passed as one argument keeps its `+` (`1+10001`). An empty value, a control character, and a key of `.` or `..` throw an `InvalidArgumentException` before any request: validate visitor input first and show your own message. Never concatenate visitor input into the `filter` argument.
- **The cookie is written through the `Storage` facade, on the default filesystem disk.** With the default `cookie_storage_path` that is `mkg/cookie.txt` under the root of that disk. It is a live ERP session: make sure the default disk is not public. In plain PHP the `FileCookieStore` creates the file with `0600` in a directory of `0700` and replaces it in one step; give it a directory only the application user can write to, not `/tmp`. When the default disk is remote (`s3`), every new service instance reads the cookie from there. Services are not singletons, but they share the session through this file.
- **On Laravel 11 the container hands every resolved service its own default Guzzle client.** The first constructor argument is `?Client $client = null`, and the Laravel 11 container fills an optional class argument with a new object. The package then never builds its configured client: `verify_ssl`, the timeouts, `log_requests` and `slow_request_seconds` do nothing and redirects are followed. Bind the services in a service provider: `$this->app->bind(DebtorsService::class, fn ($app) => new DebtorsService(logger: $app->make(LoggerInterface::class)))`. Laravel 12 and 13 leave the argument null and are not affected.
- **Your own Guzzle `Client` replaces everything the package configures on it.** `verify_ssl`, `timeout`, `connect_timeout`, `log_requests` and `slow_request_seconds` only apply to the client the package builds. A custom client needs `http_errors` on (the `401` retry and `MkgHttpException` depend on the exception) and should set `allow_redirects` to false and its own timeouts.
- **No log lines in plain PHP without a logger.** In Laravel the container injects the PSR-3 logger. With `new DebtorsService(config: $config)` nothing is logged, slow calls included, until you pass `logger:`.
- **`ArrayConfigProvider` takes flat, dotted keys** such as `'mkg.host'`, not a nested `['mkg' => [...]]` array, and it does not fall back to the environment for a key you leave out.
- **The package shares the config key `mkg` with `darvis/mkg-api`.** They cannot live in one application.

## Finding a stalling sync

MKG traffic is plain Guzzle, so Debugbar's `http_client` collector and other tools that hook Laravel's HTTP client show no requests. Set `MKG_LOG_REQUESTS=true` for a debug line per call:

```
MKG request completed. {"method":"GET","path":"/mkg/web/v3/MKG/Documents/vorr","status":200,"seconds":0.512}
```

The context holds `method`, `path`, `status` and `seconds`; the query string, and with it the filter, is not logged. A call at or above `MKG_SLOW_REQUEST_SECONDS` (default 10) is a warning `MKG request was slow.` even with request logging off, and a failed call is always a warning `MKG request failed.`. The login call is logged as well. A stall is usually many ordinary calls in a row, for example one lookup per order line, so add up the lines before blaming a single call.

## Settings

The package reads its settings through a `Darvis\MkgClient\Contracts\ConfigProviderInterface`. The default is a chain of `LaravelConfigProvider` and `EnvConfigProvider`: the Laravel config first, and the `MKG_*` environment variable when that value is null. Don't read the `mkg` config for package decisions in host code; pass your own provider when the values come from somewhere else, for example per tenant:

```php
use Darvis\MkgClient\Config\ArrayConfigProvider;
use Darvis\MkgClient\Services\DebtorsService;

$service = new DebtorsService(config: new ArrayConfigProvider([
    'mkg.host' => $tenant->mkg_host,
    'mkg.customer' => $tenant->mkg_customer,
    'mkg.username' => $tenant->mkg_username,
    'mkg.password' => $tenant->mkg_password,
    'mkg.cookie_storage_path' => 'mkg/cookie-'.$tenant->id.'.txt',
]), logger: logger());
```

Give every tenant its own `cookie_storage_path`, or they overwrite each other's session. The constructor order is `client`, `config`, `cookieStore`, `logger`; use named arguments.

| Key | Environment variable | Default |
| --- | --- | --- |
| `mkg.client_path` | `MKG_CLIENT_PATH` | `mkg` |
| `mkg.connect_timeout` | `MKG_CONNECT_TIMEOUT` | `10` |
| `mkg.cookie_storage_path` | `MKG_COOKIE_STORAGE_PATH` | `mkg/cookie.txt` |
| `mkg.customer` | `MKG_CUSTOMER` | none, required |
| `mkg.host` | `MKG_HOST` | none, required unless both URLs are set |
| `mkg.log_requests` | `MKG_LOG_REQUESTS` | `false` |
| `mkg.password` | `MKG_PASSWORD` | none, required |
| `mkg.slow_request_seconds` | `MKG_SLOW_REQUEST_SECONDS` | `10` |
| `mkg.timeout` | `MKG_TIMEOUT` | `30` |
| `mkg.url_auth` | `MKG_URL_AUTH` | derived from the host |
| `mkg.url_prod` | `MKG_URL_PROD` | derived from the host |
| `mkg.username` | `MKG_USERNAME` | none, required |
| `mkg.verify_ssl` | `MKG_VERIFY_SSL` | `true` |

Publish tags: `mkg-config` (the config file), `mkg-csv` (the CSV files into `storage/mkg/`) and `mkg-client` (both).

## Testing

Never call an MKG installation from a test. `Http::fake()` does not see this package, because it does not use Laravel's HTTP client. Hand the service a Guzzle client with a `MockHandler`, and put a cookie in a faked disk so the queue needs no login response:

```php
use Darvis\MkgClient\Services\DebtorsService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;

Storage::fake();
Storage::put('mkg/cookie.txt', 'JSESSIONID=test');

config([
    'mkg.host' => 'mkg.test',
    'mkg.customer' => 'test-customer',
    'mkg.username' => 'test-user',
    'mkg.password' => 'test-password',
]);

$history = [];
$stack = HandlerStack::create(new MockHandler([
    new Response(200, [], json_encode(['response' => ['ResultData' => [['debi' => [
        ['debi_num' => '10001', 'debi_naam' => 'Example BV', 'debi_actief' => 'WAAR'],
    ]]]]])),
]));
$stack->push(Middleware::history($history));

app()->bind(DebtorsService::class, fn () => new DebtorsService(
    client: new Client(['handler' => $stack]),
));

$rows = app(DebtorsService::class)->findDebtorRowsByDebtorNumber(10001);

expect($rows[0]['debi_num'])->toBe('10001');
expect($rows[0]['debi_actief'])->toBeTrue();

parse_str($history[0]['request']->getUri()->getQuery(), $query);
expect($query['Filter'])->toBe('debi_num = 10001');
```

- Set the four config values before the service is resolved, or the constructor throws.
- Queue one response per request. The combined searches send two requests for digits (`findDebtorRowsByNumberNameOrEmail()`, `findContactpersonRowsByNumberNameOrEmail()`) and always two in `findArticleRowsByCodeOrName()` and `findUserRowsByCodeOrName()`.
- Without the cookie in storage the first queued response is the login: `new Response(200, ['Set-Cookie' => 'JSESSIONID=test; Path=/mkg; HttpOnly'])`.
- For the retry path queue a `401` with the body `{"status_code":401,"status_txt":"Not authenticated"}`, then the login response, then the real response, and expect three requests in the history.
- For a redirect or a login page queue `new Response(302, ['Location' => '/login'])` or `new Response(200, [], '<html></html>')` and expect `MkgHttpException`. An empty result is `new Response(200, [], '{"response":{"ResultData":[{}]}}')`; an empty body on a read throws.
- For the wrong URL path queue `new Response(403, [], '<!doctype html><html></html>')` and expect `MkgHttpException`. The history then holds one request, which proves there was no retry.
- When the MKG call is not what the test is about, replace the service: `$this->mock(DebtorsService::class)->shouldReceive('findDebtorRowsByDebtorNumber')->andReturn([...])`. A mock skips the constructor, so it needs no config.
