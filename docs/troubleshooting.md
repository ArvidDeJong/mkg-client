---
title: Troubleshooting
description: "What a 401 and a 403 from the MKG API mean, what a redirect or a non JSON answer means, why a sync stalls, why rows go missing, and what to do about missing config values and TLS errors."
nav_order: 6
---

# Troubleshooting

## 401 and 403 mean different things

| Response | Cause | What the client does |
| --- | --- | --- |
| `401` with JSON `{"status_code":401,"status_txt":"Not authenticated"}` | The session cookie expired or was never valid. | Drops the cached cookie, logs in again and retries once. |
| `403` with a Tomcat HTML error page | The request never reached the REST API: the base URL path is wrong or retired. | Throws `MkgHttpException` naming the configured base and the expected path. |

A `403` is never a session problem, so re-authenticating on it only doubles the traffic against the customer's ERP and fails again. The retired `/mkg/rest/v1` and the plausible-looking `/mkg/rest/v3` both produce it; let the client derive the URLs from `MKG_HOST` instead of configuring them by hand. The reasoning is spelled out in the [FAQ](faq.md).

## A redirect or an answer that is not JSON

The client throws `MkgHttpException` when MKG answers with a redirect (`3xx`; the client never follows one) or with a `2xx` whose body is not valid JSON, such as a login page or the error page of a proxy. Up to 1.2.1 such an answer came back as an empty array, which looks exactly like "no rows" and lets a sync conclude that everything is gone.

| Response | Cause | What the client does |
| --- | --- | --- |
| `3xx` | A proxy or a login page sits in front of MKG, or the base URL points at the wrong place. | Throws `MkgHttpException` with the status code and the configured base. |
| `2xx` with HTML, text or an empty body on a read | The answer did not come from the REST API. | Throws `MkgHttpException` with the status code, the content type and the size of the body. |
| `2xx` with `[]`, `{}` or an envelope without rows | A genuine empty result. | Returns the decoded array; the `find…Rows…` methods return `[]`. |

The message never contains the body or the `Location` header, because both can carry a session id. The call is also logged as the warning `MKG response was not usable.`, with or without request logging. `$e->getResponse()` holds the response when you need to look at it. Catch the exception around a sync and stop the run; do not treat it as an empty result.

## A lookup throws an InvalidArgumentException

The number lookups (`findHeaderByOrderNumber()`, `findByDebtorNumber()`, `findByRelationNumber()` and the like) throw before any request is sent when the value is empty. The text lookups throw when the value contains a control character (a line break, a tab, a NUL byte). The `get…ByPrimaryKey()` methods throw on an empty key and on `.` or `..`. Validate the input of a visitor before it reaches the client and show your own message.

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
- The cached `JSESSIONID` gives access to the ERP for as long as it is valid. In Laravel it is written through the `Storage` facade to the default filesystem disk (`FILESYSTEM_DISK`), as `mkg/cookie.txt` under the root of that disk: that disk must not be public. With the `local` disk that is `storage/app/private/mkg/cookie.txt` (`storage/app/mkg/cookie.txt` in an app created before Laravel 11); keep it out of version control. In plain PHP the file is created for the owner only (`0600`, in a directory of `0700`); point `cookie_storage_path` at a directory that only your application user can write to.
- Keep TLS verification enabled in production.
