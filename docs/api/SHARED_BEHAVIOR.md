# Shared Behavior

All services extend `Darvis\MkgClient\BaseMkgService`.

## What all services share

- automatic authentication
- session cookie reuse and refresh on 401
- CSV-driven field metadata loading
- row extraction from MKG `response.ResultData`
- type normalization based on metadata

## Request model used by all services

- `MKG_URL_AUTH` is used as login URL
- `MKG_URL_PROD` is used as base URL for resource calls
- service paths are appended to `MKG_URL_PROD` (for example `/debi`, `/arti`, `/vorh`)
- `X-CustomerID` is sent on login and follow-up requests
- MKG `JSESSIONID` is cached and reused across requests
- on `401`, the package clears the cookie, logs in again, and retries once

## Common query parameters

List/search methods map to MKG query options:

- `FieldList`
- `Filter`
- `NumRows`
- `SkipRows` (when passed by the calling method)
- `Sort`
