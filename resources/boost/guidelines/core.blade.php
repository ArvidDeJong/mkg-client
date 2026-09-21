## darvis/mkg-client

This package is a PHP client for the REST API of MKG Software, the Dutch ERP system. It logs in through MKG's Tomcat form login, keeps the `JSESSIONID` session, and exposes a typed service per MKG document. It uses plain Guzzle, not Laravel's HTTP client.

- Config lives under the key `mkg` (file `config/mkg.php`). Set `MKG_HOST`, `MKG_CUSTOMER`, `MKG_USERNAME` and `MKG_PASSWORD`; the client derives the REST base `https://{host}/mkg/web/v3/MKG/Documents` and the login URL from the host. Set `MKG_URL_AUTH` and `MKG_URL_PROD` only for an installation that deviates from the standard layout, and never put `/mkg/rest/v1` or `/mkg/rest/v3` in them: those paths return a `403`.
- Resolve a service from the container and call its typed methods instead of building MKG URLs or query strings by hand:

@verbatim
<code-snippet name="Query MKG through a service" lang="php">
use Darvis\MkgClient\Services\DebtorsService;
use Darvis\MkgClient\Services\OrdersService;

$rows = app(DebtorsService::class)->findDebtorRowsByNumberNameOrEmail('10001');

$orders = app(OrdersService::class)->listHeaders(
    filter: 'vorh_num = VK2606096',
    sort: '-vorh_dat_order',
    numRows: 1000,
    skipRows: 0,
);
</code-snippet>
@endverbatim

- Services: `ArticleService` (arti), `DebtorsService` (debi), `ContactpersonService` (cprs), `OrdersService` (vorh, vorr, vopa), `AddressesService` (adrs), `RelationsService` (rela) and `UserService` (gebr), all under `Darvis\MkgClient\Services`. Methods named `find…Rows…` return the flat rows; the plain `find…` methods return the raw MKG response.
- MKG caps a result set at 1000 rows per call and returns 100 when `NumRows` is omitted, without any error when more exist. Page with `skipRows` and `numRows` on the order list methods (`listHeaders()`, `listRows()`, `listRowParameters()`), and pass `sort` so the order is stable across pages; narrow the other documents with a filter.
- A `401` with a JSON body means the session expired; the client logs in again and retries once by itself. A `403` with an HTML body means the URL path is wrong and never reached the API; the client throws `Darvis\MkgClient\Exceptions\MkgHttpException` naming the configured base. Don't add a retry on `403` and don't treat it as a permission problem.
- A redirect (`3xx`, never followed) and a `2xx` whose body is not JSON also throw `MkgHttpException`, and are logged as the warning `MKG response was not usable.`. An empty array therefore means that MKG answered and found nothing; catch the exception around a sync and stop the run instead of treating it as "no rows".
- Filters use MKG's own syntax in the `filter` argument (`debi_num = 10001`, `vorh_num = VK2606096`), and field names stay exactly as MKG spells them (`debi_num`, `vorh_dat_order`). A `filter` string is sent as written: never build one from visitor input without validating it. Prefer the typed lookups for that: they send a plain number as it is, compare any other value as a quoted and escaped text (`vorh_num = "VK2606096"`), URL-encode the parts of a primary key, and throw an `InvalidArgumentException` before any request on an empty value, a control character, or a key of `.` or `..`.
- Field metadata comes from the CSV files in `resources/csv/` (published to `storage/mkg/` with `php artisan vendor:publish --tag=mkg-csv`). A field in a service's built-in default list that is missing there is left out of the request silently. A `fieldList` you pass is sent as written; a field in it that the CSV does not know comes back unconverted. Pass a `fieldList` for orders, addresses and relations: their default is every field in the CSV (355 for `vorr`).
- The `find…Rows…` and `extract…Rows()` methods convert by CSV type: `integer` to int, `decimal`, `percentage` and `bedrag` to float, `logical` to bool, `datum` to `Y-m-d`. Other types, `aantal` (quantities) included, stay as MKG sent them, and `debi_num` and `vorh_num` are strings.
- Only a `4xx` on a document request, a redirect and a non-JSON answer are `MkgHttpException`. A `5xx`, a timeout and a failed login call are Guzzle's own exceptions; catch `GuzzleHttp\Exception\GuzzleException` after `MkgHttpException`.
- Services are not singletons; each `app()` call builds a new one and they share the session through the cookie file. The package binds its own services, so they always get the client it configures. A service class of your own that extends one of them is not bound: on Laravel 11 the container then passes a bare Guzzle client and `verify_ssl`, the timeouts, `log_requests` and the redirect setting do not apply. Bind it yourself with `new YourService(logger: $app->make(\Psr\Log\LoggerInterface::class))`.
- To see where a sync stalls, set `MKG_LOG_REQUESTS=true`; every call is logged with method, path, status and duration, and a call slower than `MKG_SLOW_REQUEST_SECONDS` is logged as a warning even when request logging is off. Debugbar's HTTP client collector never sees MKG traffic because it is plain Guzzle.
- In plain PHP, construct a service with an `ArrayConfigProvider` (keys `mkg.host`, `mkg.customer`, `mkg.username`, `mkg.password`) and optionally a PSR-3 logger; in Laravel the provider, the `LaravelCookieStore` and the logger are wired for you. The `LaravelCookieStore` writes the cookie through the `Storage` facade to the default filesystem disk (`mkg/cookie.txt` under its root). It is a live ERP session, so the default disk must not be public. In plain PHP the `FileCookieStore` creates the file for the owner only (`0600`); point `mkg.cookie_storage_path` at a directory only the application user can write to.
