---
title: "Troubleshooting"
description: "Every exception message and log line of darvis/mkg-client with cause and fix: 401, 403, redirects, missing config, missing rows, TLS errors and your own service classes."
nav_order: 7
---

# Troubleshooting

Each section starts with the message or the symptom, then the cause, then the fix. The messages are quoted from the source, so you can search this page for the text in your log.

## Missing MKG configuration values

```
RuntimeException: Missing MKG configuration values: mkg.customer, mkg.password
RuntimeException: Missing MKG configuration values: set mkg.host (for example "saas1.mkg.eu"), or set mkg.url_auth explicitly.
RuntimeException: Missing MKG configuration values: set mkg.host (for example "saas1.mkg.eu"), or set mkg.url_prod explicitly.
```

**Cause.** The first message: `MKG_CUSTOMER`, `MKG_USERNAME` or `MKG_PASSWORD` is empty. The second: there is no `MKG_HOST` and no `MKG_URL_AUTH`. Both are thrown when a service is created, before any request. The third is thrown at the first request: `MKG_URL_AUTH` is set, but there is no `MKG_HOST` and no `MKG_URL_PROD` to send the request to.

**Fix.** Fill the values in `.env` and clear a cached config:

```bash
php artisan config:clear
```

Because a service checks its config when it is created, a class that takes a service in its constructor fails on a machine without `MKG_*` values, CI included. Resolve the service where you use it, or set the values in the test; see [Testing](testing.md).

## MKG returned 403 with an HTML error page

```
MKG returned 403 with an HTML error page, which means the request never reached the REST API: the base URL path is wrong or no longer exists. Configured base: … Expected the path to end in "/web/v3/MKG/Documents" (note: "web/v3", not "rest/v3" or "rest/v1"). Set mkg.host and let the client build the URL.
```

**Cause.** The `403` comes from Tomcat, the web server in front of MKG, not from MKG itself. The URL path does not exist. It is not a permission problem and not an expired session. `/mkg/rest/v1` and `/mkg/rest/v3` both produce it.

**Fix.** Remove `MKG_URL_AUTH` and `MKG_URL_PROD` from `.env`, set `MKG_HOST`, and for a training environment `MKG_CLIENT_PATH=mkgoefenclient`. The client does not retry a `403` and keeps the session cookie. Do not add a retry or a new login around it: that doubles the traffic against the ERP and fails again.

## MKG returned 401: Not authenticated

```
MKG returned 401: Not authenticated. Original error: …
```

**Cause.** MKG answers `401` with the JSON body `{"status_code":401,"status_txt":"Not authenticated"}` when the session cookie has expired. The client handles that by itself: it deletes the cookie, logs in again and repeats the request once. You only see this exception when the repeated request gets a `401` too, which means the new login did not give a valid session.

**Fix.** Check `MKG_USERNAME`, `MKG_PASSWORD` and `MKG_CUSTOMER`, and whether the MKG user is still active. Test the login on its own with [Verification](verification.md).

## MKG answered 302, a redirect, or a body that is not valid JSON

```
MKG answered 302, a redirect, where a JSON document was expected. The client does not follow redirects: check the configured base URL (…) and whether a proxy or login page sits in front of MKG.
MKG answered 200 with a body that is not valid JSON (Content-Type: text/html, 1523 bytes), so it is not an empty result. Check the configured base URL (…) and whether a proxy or login page sits in front of MKG.
```

**Cause.** Something other than the MKG API answered: a proxy, a login page, a maintenance page. Up to version 1.2.1 such an answer came back as an empty array.

| Response | What the client does |
| --- | --- |
| `3xx` | Throws `MkgHttpException` with the status code and the configured base. |
| `2xx` with HTML, text, or an empty body on a read | Throws `MkgHttpException` with the status code, the content type and the size of the body. |
| `2xx` with `[]`, `{}` or a response without rows | A real empty result: the `find…Rows…()` methods return `[]`. |

**Fix.** Check the base URL and what sits between your server and MKG. The message never contains the body or the `Location` header, because both can carry a session id; `$e->getResponse()` holds the response when you need to look at it. Catch the exception around a sync and stop the run. Do not treat it as "no rows".

## A 5xx, a timeout or a failed login is not an MkgHttpException

**Symptom.** `catch (MkgHttpException $e)` does not catch the error.

**Cause.** Only a `4xx` on a document request, a redirect and a non-JSON answer are wrapped. A `5xx` is Guzzle's `ServerException`, a timeout or a refused connection is a `ConnectException`, and a `4xx` or `5xx` on the login call is Guzzle's own `ClientException` or `ServerException`. None of these is retried.

**Fix.** Catch `GuzzleHttp\Exception\GuzzleException` after `MkgHttpException`, as the [example](usage.md#a-complete-example-a-debtor-and-its-latest-orders) does.

## A lookup throws an InvalidArgumentException

```
The MKG filter value for "vorh_num" is empty.
The MKG filter value for "debi_naam" contains a control character (a line break, a tab or a NUL byte).
An MKG path segment must not be empty, "." or "..", got "..".
```

**Cause.** The lookups by number refuse an empty value, the text searches refuse a control character, and the `get…ByPrimaryKey()` methods refuse an empty key part, `.` and `..`. All three are thrown before a request is sent.

**Fix.** Validate the input of a visitor before it reaches the client, and show your own message.

## Rows are missing without an error

**Cause.** MKG returns 100 rows when `NumRows` is left out and never more than 1000 per call. It does not say that more rows exist. The name, email and combined searches ask for 25 rows unless you pass `numRows`.

**Fix.** Pass a `numRows`, and page with `skipRows` and a `sort`; see [Read more than 1000 rows](usage.md#read-more-than-1000-rows).

## A field is missing from the result, or is not converted

**Cause.** One of three things:

- You rely on the default field list of `ArticleService`, `DebtorsService`, `ContactpersonService` or `UserService`. That is a short built-in list, and a field in it that the CSV file does not know is left out of the request without a message.
- You passed your own `fieldList` with a field the CSV does not know. It is requested, but comes back unconverted.
- The field has a type that is not converted, such as `aantal`. See [Which values are converted](usage.md#which-values-are-converted).

**Fix.** Pass the field in `fieldList`. To have it converted, publish the CSV files with `php artisan vendor:publish --tag=mkg-csv` and add the field to `storage/mkg/{document}.csv`, then restart queue workers and Octane: the CSV is read once per process.

## Fields look out of date after a package update

**Cause.** A published `storage/mkg/{document}.csv` wins over the file in the package, also after an update.

**Fix.** Compare it with `vendor/darvis/mkg-client/resources/csv/`, or delete the published copy.

## A sync stalls or takes forever

**Cause.** Usually many ordinary calls in a row, for example one lookup per order line, or a call with a default field list of hundreds of fields. Tools that hook Laravel's HTTP client do not show MKG traffic, because the package uses Guzzle directly.

**Fix.** Set `MKG_LOG_REQUESTS=true` and read the log; see [See every call in the log](usage.md#see-every-call-in-the-log). Pass a short `fieldList`; see [Ask only for the fields you need](usage.md#ask-only-for-the-fields-you-need). A call that takes `MKG_SLOW_REQUEST_SECONDS` or longer is logged as the warning `MKG request was slow.` even when request logging is off.

## The timeout, TLS and logging settings have no effect on a service of your own

**Symptom.** `MKG_TIMEOUT`, `MKG_CONNECT_TIMEOUT`, `MKG_VERIFY_SSL=false` and `MKG_LOG_REQUESTS` do nothing, and redirects are followed, for a service class you wrote yourself by extending a package service or `BaseMkgService`. On Laravel 11 only.

**Cause.** The first constructor argument of a service is an optional Guzzle client. The Laravel 11 container fills an optional class argument with a new object, here a Guzzle client with Guzzle's own defaults, so the package never builds its configured client. The package binds its own seven services to prevent this, so they are not affected. Up to 1.3.1 they were: upgrade. Laravel 12 and 13 leave the argument empty.

**Fix.** Bind your own service the same way, so the container does not pass a client.

File: `app/Providers/AppServiceProvider.php`

```php
use App\Services\Mkg\InvoicesService;
use Psr\Log\LoggerInterface;

public function register(): void
{
    $this->app->bind(InvoicesService::class, fn ($app) => new InvoicesService(logger: $app->make(LoggerInterface::class)));
}
```

`new InvoicesService(logger: $logger)` anywhere else has the same effect.

## Nothing is logged in plain PHP

**Cause.** Without Laravel there is no logger until you pass one.

**Fix.** Pass a PSR-3 logger: `new DebtorsService(config: $config, logger: $logger)`.

## TLS certificate errors

**Cause.** The certificate of the MKG host is self-signed or not trusted by your server.

**Fix.** For local development set `MKG_VERIFY_SSL=false` and run `php artisan config:clear`. Keep it `true` in production; a trusted certificate is part of the MKG API setup. The setting has no effect on a Guzzle client you pass yourself.

## Two applications or tenants share one session

**Cause.** Every service reads and writes the cookie at `cookie_storage_path`. Two MKG installations with the same path overwrite each other's session.

**Fix.** Give every installation its own `mkg.cookie_storage_path`; see [Plain PHP](usage.md#plain-php).

## The package conflicts with darvis/mkg-api

**Cause.** Both packages use the config key `mkg` and the same environment variables.

**Fix.** Use one of the two in an application.

## Keep the credentials and the session safe

- Keep `MKG_PASSWORD` and the API key in `.env` or a secret store, never in the repository.
- The cached `JSESSIONID` gives access to the ERP for as long as it is valid. In Laravel it is on the default filesystem disk, which must not be public. See [Where the session cookie is stored](installation.md#where-the-session-cookie-is-stored).
- Keep TLS verification on in production.
