---
title: Verification
description: "Check connectivity and credentials against an MKG installation with curl or the MKG Postman collection before writing application code."
nav_order: 5
---

# Verification

Use this page to validate connectivity and authentication before integrating in application code. The variables below are the same ones the package reads; `MKG_URL_AUTH` and `MKG_URL_PROD` are the derived URLs from [Installation & configuration](installation.md#environment-variables).

## Verify with curl

### 1. Log in and save the cookie

```bash
curl -k -sS -X POST "$MKG_URL_AUTH" \
  -H "Accept: application/json" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "j_username=$MKG_USERNAME" \
  --data-urlencode "j_password=$MKG_PASSWORD" \
  --cookie-jar /tmp/mkg-cookie.txt -D -
```

Expected: HTTP 200 and a `Set-Cookie` header containing `JSESSIONID`.

### 2. Check the authenticated context

```bash
MKG_REST_BASE="${MKG_URL_PROD%/Documents}"
curl -k -sS "$MKG_REST_BASE/User" \
  -H "Accept: application/json" \
  -H "X-CustomerID: $MKG_CUSTOMER" \
  --cookie /tmp/mkg-cookie.txt
```

Expected: HTTP 200 with the user context as JSON.

### 3. Run a simple GET request

```bash
curl -k -sS "$MKG_URL_PROD/rela?NumRows=5&FieldList=rela_num,rela_naam" \
  -H "Accept: application/json" \
  -H "X-CustomerID: $MKG_CUSTOMER" \
  --cookie /tmp/mkg-cookie.txt
```

Expected: HTTP 200 with JSON under `response.ResultData`. A `403` with an HTML body here means the base URL is wrong; see [Troubleshooting](troubleshooting.md#401-and-403-mean-different-things).

### 4. Optional write test with rollback

Run this only in a test or training environment, or on a dedicated test record.

1. Read and store the current value of a harmless text field, for example `rela_memo`.
2. Update it with a temporary marker value.
3. Verify the new value.
4. Roll back to the original value.

```bash
curl -k -sS -X PUT "$MKG_URL_PROD/rela/1" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "X-CustomerID: $MKG_CUSTOMER" \
  --cookie /tmp/mkg-cookie.txt \
  -d '{"request": {"InputData": {"rela": [{"rela_memo": "API verification marker"}]}}}'
```

Send the same call with the original value to roll back. Expected: HTTP 200 for both calls and the original value restored.

If your TLS certificate is valid and trusted, drop `-k`.

## Verify with Postman

MKG publishes a collection and a public workspace:

- [MKG API Postman collection page](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/6032/api-postman-collectie)
- [MKG Postman public workspace](https://www.postman.com/mkg-nederland-bv/)

Fork the MKG API collection and an environment template, keep your fork in sync with pull changes, and apply custom changes in copied requests rather than in the base fork.

## Official MKG resources

- [MKG API introduction](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/4767/inleiding-tot-de-mkg-api)
- [MKG Getting Started](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/6598/getting-started)
- [Official MKG API call guide](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/10924/hoe-werken-mkg-api-aanroepen)
