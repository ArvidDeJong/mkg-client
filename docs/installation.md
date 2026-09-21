---
title: "Installation & configuration"
description: "Install darvis/mkg-client step by step: what to ask MKG for, the four .env values, every config key, the publish tags, and a command that proves it works."
nav_order: 2
---

# Installation & configuration

## Requirements

- PHP 8.2 or higher
- An MKG installation with the API switched on, and API credentials for it
- Laravel 11, 12 or 13, only when you use the Laravel integration

The package itself depends on `guzzlehttp/guzzle` (the HTTP client it sends its requests with) and `psr/log`. Composer installs both.

## What you need from MKG

The client can only connect when the MKG side is prepared. Ask the MKG administrator of the company for these five things:

1. The MKG API is set up (Tomcat, the API and an SSL certificate).
2. An MKG Exchange license is active.
3. An API application exists in MKG and an API key is generated for it. That key is your `MKG_CUSTOMER` value; the client sends it as the `X-CustomerID` header.
4. A dedicated MKG user exists for the API, with permission to read the documents you need. Its name and password are `MKG_USERNAME` and `MKG_PASSWORD`.
5. The hostname under which the API is reachable, with the port when it is not 443. That is `MKG_HOST`.

MKG's own checklist: [MKG Getting Started](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/6598/getting-started).

## Install in Laravel

1. Install the package:

   ```bash
   composer require darvis/mkg-client
   ```

   Laravel registers the service provider by itself (package discovery). There are no migrations, routes or views.

2. Add the four values to `.env`:

   ```dotenv
   MKG_HOST=your-mkg-host
   MKG_CUSTOMER=your-api-key
   MKG_USERNAME=your-api-username
   MKG_PASSWORD=your-api-password
   ```

   `MKG_HOST` is a hostname without `https://`, optionally with a port. [The URLs are built from the host](#the-urls-are-built-from-the-host) has an example per kind of installation.

3. Clear a cached config, otherwise Laravel keeps using the old values:

   ```bash
   php artisan config:clear
   ```

4. Make sure the default filesystem disk (`FILESYSTEM_DISK`, `local` in a new application) is not public. The client stores the MKG session cookie there; see [Where the session cookie is stored](#where-the-session-cookie-is-stored).

5. [Check that it works](#check-that-it-works).

For plain PHP, without Laravel, see [Usage](usage.md#plain-php).

## Check that it works

Run this once. It logs in and asks MKG for one debtor:

```bash
php artisan tinker --execute="dump(app(Darvis\MkgClient\Services\DebtorsService::class)->extractDebtorRows(app(Darvis\MkgClient\Services\DebtorsService::class)->list(['debi_num', 'debi_naam'], numRows: 1)));"
```

You should see one row, with your own data:

```
array:1 [
  0 => array:2 [
    "debi_num" => "10001"
    "debi_naam" => "Example BV"
  ]
]
```

| What you see | What it means |
| --- | --- |
| One row | The URL, the API key and the login are right. |
| `[]` | The connection works, but the API user sees no debtors. Check the permissions of the MKG user. |
| `RuntimeException: Missing MKG configuration values: …` | A value in `.env` is empty, or the config is cached. See [Troubleshooting](troubleshooting.md#missing-mkg-configuration-values). |
| `MkgHttpException: MKG returned 403 with an HTML error page …` | The URL path is wrong. See [Troubleshooting](troubleshooting.md#mkg-returned-403-with-an-html-error-page). |
| `MkgHttpException: MKG returned 401: Not authenticated …` | The login did not give a valid session. See [Troubleshooting](troubleshooting.md#mkg-returned-401-not-authenticated). |
| `cURL error 60` or another TLS message | The certificate of the MKG host is not trusted. See [Troubleshooting](troubleshooting.md#tls-certificate-errors). |

To test the URL and the credentials without PHP, use [Verification](verification.md).

## The URLs are built from the host

| Installation | `.env` |
| --- | --- |
| On premise | `MKG_HOST=mkgapi.yourdomain.local:443` |
| MKG Cloud | `MKG_HOST=saasX.mkg.eu:443` |
| Training environment | `MKG_HOST=saasX-oefen.mkg.eu:443` and `MKG_CLIENT_PATH=mkgoefenclient` |

From the host the client builds two URLs:

| | |
| --- | --- |
| REST base | `https://{host}/{client_path}/web/v3/MKG/Documents` |
| Login | `https://{host}/{client_path}/static/auth/j_spring_security_check` |

`{client_path}` is `mkg`, or `mkgoefenclient` for a training environment. The client always uses `https://`; a scheme you put in `MKG_HOST` is removed.

Keep these paths out of `.env`. A typo in the path is answered with a `403` and a Tomcat HTML error page, which reads like a permission problem. `/mkg/rest/v1` and `/mkg/rest/v3` both fail that way. `MKG_URL_AUTH` and `MKG_URL_PROD` override the two URLs for an installation with a different layout. When you override, set both: with only `MKG_URL_AUTH` and no `MKG_HOST`, the service is created without an error and the first request throws a `RuntimeException`.

## Every setting

All keys live in `config/mkg.php`, in alphabetical order. You only need the file when you want to change a value in PHP instead of `.env`.

| Key | Env variable | Default | Description |
| --- | --- | --- | --- |
| `client_path` | `MKG_CLIENT_PATH` | `mkg` | Client segment in the URL; `mkgoefenclient` for a training environment |
| `connect_timeout` | `MKG_CONNECT_TIMEOUT` | `10` | Connection timeout in seconds |
| `cookie_storage_path` | `MKG_COOKIE_STORAGE_PATH` | `mkg/cookie.txt` | Where the `JSESSIONID` is cached: in Laravel relative to the root of the default filesystem disk; in plain PHP a file path |
| `customer` | `MKG_CUSTOMER` | | The API key, sent as `X-CustomerID`. Required |
| `host` | `MKG_HOST` | | Hostname of the installation, optionally with a port, without scheme. Required unless both URLs are set |
| `log_requests` | `MKG_LOG_REQUESTS` | `false` | Log every call at debug level; see [Usage](usage.md#see-every-call-in-the-log) |
| `password` | `MKG_PASSWORD` | | Password of the API user. Required |
| `slow_request_seconds` | `MKG_SLOW_REQUEST_SECONDS` | `10` | A call that takes this long or longer is logged as a warning, even when `log_requests` is off |
| `timeout` | `MKG_TIMEOUT` | `30` | Request timeout in seconds |
| `url_auth` | `MKG_URL_AUTH` | built from the host | Override of the login URL |
| `url_prod` | `MKG_URL_PROD` | built from the host | Override of the REST base |
| `username` | `MKG_USERNAME` | | Name of the API user. Required |
| `verify_ssl` | `MKG_VERIFY_SSL` | `true` | Verify the TLS certificate; keep it on in production |

`connect_timeout`, `log_requests`, `slow_request_seconds`, `timeout` and `verify_ssl` configure the Guzzle client that the package builds. They do nothing when you pass your own client to a service; see [Usage](usage.md#your-own-guzzle-client).

## Publish the config or the CSV files

Publishing copies a file from the package into your application so you can change it. You do not have to publish anything to get started.

```bash
php artisan vendor:publish --tag=mkg-config     # config/mkg.php
php artisan vendor:publish --tag=mkg-csv        # the CSV files, into storage/mkg/
php artisan vendor:publish --tag=mkg-client     # both
```

A published CSV file wins over the one in the package, also after a package update. When fields or types look out of date after an update, compare `storage/mkg/` with `vendor/darvis/mkg-client/resources/csv/`, or delete the published copy.

## What the CSV files are for

Every MKG document has a CSV file with its fields, labels and types, exported from MKG: `adrs`, `arti`, `cprs`, `cred`, `debi`, `gebr`, `rela`, `vopa`, `vorh` and `vorr`. The services use them for two things: the default list of fields to request, and the conversion of values to PHP types. The client looks in this order:

1. `storage/mkg/{document}.csv`, your published copy;
2. `resources/csv/{document}.csv` in the package.

A field that is in a service's built-in default list but not in the CSV is left out of the request without a message. A `fieldList` you pass yourself is sent exactly as you wrote it; a field in it that the CSV does not know comes back unconverted.

The metadata is read once per PHP process. A queue worker or Octane needs a restart to see a changed CSV file.

## Where the session cookie is stored

The `JSESSIONID` cookie is a live session on the ERP. Whoever can read it can read the ERP data until the session ends.

- **Laravel.** The client writes it through the `Storage` facade to the default filesystem disk, as `mkg/cookie.txt` under the root of that disk. With the `local` disk of a new application that is `storage/app/private/mkg/cookie.txt`. The default disk must not be `public` or a public bucket. When it has to be, construct the services with your own `Darvis\MkgClient\Contracts\CookieStoreInterface` that writes somewhere private.
- **Plain PHP.** `FileCookieStore` creates the file with mode `0600` in a new directory with mode `0700`. A relative `cookie_storage_path` lands in the system temp directory; set an absolute path in a directory that only your application user can write to.

Services are not singletons: `app(DebtorsService::class)` gives a new object every time. They share the session through the cookie file, so a second service does not log in again.

## Laravel Boost

The package ships a [Laravel Boost](https://laravel.com/docs/boost) guideline and a `mkg-client-development` skill in `resources/boost/`. Boost gives the AI assistant in your editor the rules of the packages you have installed. Run `php artisan boost:install`, or `php artisan boost:update --discover` in a project that already uses Boost.
