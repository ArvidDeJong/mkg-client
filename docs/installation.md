---
title: "Installation & configuration"
description: "Install darvis/mkg-client: requirements, what MKG needs on its side, environment variables, the derived URLs, every config key and the CSV field metadata."
nav_order: 2
---

# Installation & configuration

## Requirements

- PHP 8.2+
- Valid MKG API credentials (see below)
- Laravel 11, 12 or 13, only when you use the Laravel integration

## What MKG needs on its side

Before the client can connect, the MKG installation has to be prepared:

- the MKG API technical setup is completed (Tomcat, API, SSL);
- an MKG Exchange license is active, read-only or CRUD depending on your use case;
- at least one API application exists in MKG and an API key is generated (that key is the `X-CustomerID` value);
- a dedicated MKG user exists with sufficient permissions;
- the API is reachable without certificate errors.

MKG's own checklist: [MKG Getting Started](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/6598/getting-started).

## Installation

```bash
composer require darvis/mkg-client
```

In Laravel the service provider is registered through package discovery. Publish the config and the CSV metadata when you want to change them:

```bash
php artisan vendor:publish --tag=mkg-client     # config and CSV files
php artisan vendor:publish --tag=mkg-config     # config only
php artisan vendor:publish --tag=mkg-csv        # CSV files only, into storage/mkg/
```

## Environment variables

```dotenv
MKG_HOST=your-mkg-host
MKG_CUSTOMER=your-customer-code
MKG_USERNAME=your-api-username
MKG_PASSWORD=your-api-password
```

Only two things differ between installations: the host (optionally with a port) and the client segment. Set those and the client builds both URLs itself.

| Installation | `.env` |
| --- | --- |
| On premise | `MKG_HOST=mkgapi.yourdomain.local:443` |
| MKG Cloud | `MKG_HOST=saasX.mkg.eu:443` |
| Training environment | `MKG_HOST=saasX-oefen.mkg.eu:443` and `MKG_CLIENT_PATH=mkgoefenclient` |

Each of these resolves to:

| | |
| --- | --- |
| REST base | `https://{host}/{client_path}/web/v3/MKG/Documents` |
| Authentication | `https://{host}/{client_path}/static/auth/j_spring_security_check` |

Keep those paths out of your environment file. They belong to the MKG API version, not to your environment, and a typo there is answered with a `403` and a Tomcat HTML error page that reads like a permission problem. The retired `/mkg/rest/v1` and the plausible-looking `/mkg/rest/v3` both fail that way. `MKG_URL_AUTH` and `MKG_URL_PROD` still override the derived values, for an installation that deviates from this layout.

## Configuration

All keys live in `config/mkg.php`, in alphabetical order.

| Key | Env variable | Default | Description |
| --- | --- | --- | --- |
| `client_path` | `MKG_CLIENT_PATH` | `mkg` | Client segment in the URL; `mkgoefenclient` for a training environment |
| `connect_timeout` | `MKG_CONNECT_TIMEOUT` | `10` | Connection timeout in seconds |
| `cookie_storage_path` | `MKG_COOKIE_STORAGE_PATH` | `mkg/cookie.txt` | Where the `JSESSIONID` is cached, relative to the Laravel storage disk or, in plain PHP, a file path |
| `customer` | `MKG_CUSTOMER` | | Customer code, sent as `X-CustomerID` |
| `host` | `MKG_HOST` | | Hostname of the installation, optionally with a port, without scheme |
| `log_requests` | `MKG_LOG_REQUESTS` | `false` | Log every call at debug level; see [Usage](usage.md#request-logging) |
| `password` | `MKG_PASSWORD` | | API password |
| `slow_request_seconds` | `MKG_SLOW_REQUEST_SECONDS` | `10` | A call slower than this is logged as a warning, even when `log_requests` is off |
| `timeout` | `MKG_TIMEOUT` | `30` | Request timeout in seconds |
| `url_auth` | `MKG_URL_AUTH` | derived | Override of the login URL |
| `url_prod` | `MKG_URL_PROD` | derived | Override of the REST base |
| `username` | `MKG_USERNAME` | | API username |
| `verify_ssl` | `MKG_VERIFY_SSL` | `true` | Verify the TLS certificate; keep it on in production |

## The request model

- Service paths such as `/debi`, `/arti` and `/vorh` are appended to the REST base.
- Every request carries `X-CustomerID` and `Accept: application/json`.
- The `JSESSIONID` cookie is cached and reused until MKG returns `401`; the client then drops it, logs in again and retries the request once.
- A `403` is never retried; see [Troubleshooting](troubleshooting.md#401-and-403-mean-different-things).

## CSV field metadata

Each MKG document has a CSV file with its fields, labels and types, exported from MKG. The services use it for their default field lists and for type normalisation. The lookup order is:

1. `storage/mkg/{document}.csv`, the application's override (published with `--tag=mkg-csv`);
2. `resources/csv/{document}.csv` in the package, the bundled fallback.

A field that is missing from the CSV is dropped from the default `FieldList` silently, so add a new field there before requesting it.
