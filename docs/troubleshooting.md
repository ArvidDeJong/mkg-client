---
title: Troubleshooting
description: "What a 401 and a 403 from the MKG API mean, why a sync stalls, why rows go missing, and what to do about missing config values and TLS errors."
nav_order: 6
---

# Troubleshooting

## 401 and 403 mean different things

| Response | Cause | What the client does |
| --- | --- | --- |
| `401` with JSON `{"status_code":401,"status_txt":"Not authenticated"}` | The session cookie expired or was never valid. | Drops the cached cookie, logs in again and retries once. |
| `403` with a Tomcat HTML error page | The request never reached the REST API: the base URL path is wrong or retired. | Throws `MkgHttpException` naming the configured base and the expected path. |

A `403` is never a session problem, so re-authenticating on it only doubles the traffic against the customer's ERP and fails again. The retired `/mkg/rest/v1` and the plausible-looking `/mkg/rest/v3` both produce it; let the client derive the URLs from `MKG_HOST` instead of configuring them by hand. The reasoning is spelled out in the [FAQ](faq.md).

## A sync stalls or takes forever

Switch on [request logging](usage.md#request-logging). Profilers that hook Laravel's HTTP client never see MKG traffic, because the client uses plain Guzzle. A stall is rarely one slow call; it is usually dozens of ordinary ones in a row, which only the sum reveals. Calls slower than `MKG_SLOW_REQUEST_SECONDS` are logged as a warning even when request logging is off.

## Rows are missing without an error

MKG returns 100 rows when `NumRows` is omitted and caps every call at 1000, silently. Ask for a `numRows` and page with `skipRows` and a stable `sort`; see [Paging](usage.md#paging).

## A field is missing from the result

The default `FieldList` of a service is filtered against the CSV metadata of the document. A field that is not in the CSV is dropped silently. Publish the CSV files with `php artisan vendor:publish --tag=mkg-csv` and add the field to `storage/mkg/{document}.csv`, or pass the field explicitly in `fieldList`.

## Missing MKG configuration values

`RuntimeException: Missing MKG configuration values: …` means one of `customer`, `username` or `password` is empty. A missing `host` without the two URL overrides throws a separate `RuntimeException` that says what to configure. Check `.env` and clear a cached config:

```bash
php artisan config:clear
```

## TLS certificate errors

An MKG environment with a self-signed certificate needs `MKG_VERIFY_SSL=false` in local development. Keep it `true` in production; a real certificate on the MKG side is part of the MKG API setup.

## Security notes

- Treat `MKG_PASSWORD` and the customer code as secrets and keep them in environment-based storage.
- The cached `JSESSIONID` gives access to the ERP for as long as it is valid; keep `storage/mkg/` out of version control and out of public disks.
- Keep TLS verification enabled in production.
