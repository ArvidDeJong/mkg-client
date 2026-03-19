# Verification

Use this guide to validate connectivity and authentication before integrating in application code.

## Verify with curl

### 1) Login and save cookies

```bash
curl -k -sS -X POST "$MKG_URL_AUTH" \
  -H "Accept: application/json" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "j_username=$MKG_USERNAME" \
  --data-urlencode "j_password=$MKG_PASSWORD" \
  --cookie-jar /tmp/mkg-cookie.txt -D -
```

Expected: HTTP 200 and a `Set-Cookie` header containing `JSESSIONID`.

### 2) Check authenticated context

```bash
MKG_REST_BASE="${MKG_URL_PROD%/Documents}"
curl -k -sS "$MKG_REST_BASE/User" \
  -H "Accept: application/json" \
  -H "X-CustomerID: $MKG_CUSTOMER" \
  --cookie /tmp/mkg-cookie.txt
```

Expected: HTTP 200 with JSON user context.

### 3) Run a simple GET request

```bash
curl -k -sS "$MKG_URL_PROD/rela?NumRows=5&FieldList=rela_num,rela_naam" \
  -H "Accept: application/json" \
  -H "X-CustomerID: $MKG_CUSTOMER" \
  --cookie /tmp/mkg-cookie.txt
```

Expected: HTTP 200 with JSON under `response.ResultData`.

### 4) Optional safe write test with rollback

Run this only in a test/training environment or on a dedicated test record.

1. Read and store the current value of a safe text field (for example `rela_memo`).
2. Update it with a temporary marker value.
3. Verify the new value.
4. Roll back to the original value.

Example update call:

```bash
curl -k -sS -X PUT "$MKG_URL_PROD/rela/1" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "X-CustomerID: $MKG_CUSTOMER" \
  --cookie /tmp/mkg-cookie.txt \
  -d '{
    "request": {
      "InputData": {
        "rela": [
          {
            "rela_memo": "API verification marker"
          }
        ]
      }
    }
  }'
```

Rollback call:

```bash
curl -k -sS -X PUT "$MKG_URL_PROD/rela/1" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "X-CustomerID: $MKG_CUSTOMER" \
  --cookie /tmp/mkg-cookie.txt \
  -d '{
    "request": {
      "InputData": {
        "rela": [
          {
            "rela_memo": "<original value>"
          }
        ]
      }
    }
  }'
```

Expected: HTTP 200 for both PUT calls and value restored after rollback.

If your TLS certificate is valid and trusted, remove `-k`.

## Verify with Postman

MKG collection resources:

- [MKG API Postman collection page](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/6032/api-postman-collectie)
- [MKG Postman public workspace](https://www.postman.com/mkg-nederland-bv/)

Recommended workflow:

1. Open the MKG Postman workspace and fork the MKG API collection.
2. Fork an MKG environment template and select it.
3. Keep your fork in sync with pull changes.
4. Apply custom changes in copied requests, not in the base fork.
