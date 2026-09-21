---
title: "Verification"
description: "Check the MKG API URL, the API key and the login with two curl commands before you write PHP, and read what a 200, a 401 and a 403 tell you about your settings."
nav_order: 6
---

# Verification

Use this page when you want to know whether the MKG side works before you involve PHP. The two curl commands send the same requests the package sends.

## Set the values in your shell

```bash
export MKG_HOST="your-mkg-host"
export MKG_CUSTOMER="your-api-key"
export MKG_USERNAME="your-api-username"
export MKG_PASSWORD="your-api-password"

export MKG_URL_AUTH="https://$MKG_HOST/mkg/static/auth/j_spring_security_check"
export MKG_URL_PROD="https://$MKG_HOST/mkg/web/v3/MKG/Documents"
```

For a training environment, replace `/mkg/` with `/mkgoefenclient/` in both URLs.

## 1. Log in and save the cookie

```bash
curl -sS -X POST "$MKG_URL_AUTH" \
  -H "X-CustomerID: $MKG_CUSTOMER" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "j_username=$MKG_USERNAME" \
  --data-urlencode "j_password=$MKG_PASSWORD" \
  --cookie-jar ./mkg-cookie.txt -D -
```

Expected: a `Set-Cookie` header that contains `JSESSIONID`. The cookie is saved in `mkg-cookie.txt` in the current directory. Delete the file when you are done; it is a live session.

## 2. Read five relations

```bash
curl -sS "$MKG_URL_PROD/rela?NumRows=5&FieldList=rela_num,rela_naam" \
  -H "Accept: application/json" \
  -H "X-CustomerID: $MKG_CUSTOMER" \
  --cookie ./mkg-cookie.txt
```

Expected: JSON with the rows under `response.ResultData`.

## What the answer tells you

| Answer | Meaning |
| --- | --- |
| JSON with rows | The host, the API key and the login are right. The same values work in `.env`. |
| `403` with an HTML page | The URL path is wrong. See [Troubleshooting](troubleshooting.md#mkg-returned-403-with-an-html-error-page). |
| `401` with `{"status_code":401,"status_txt":"Not authenticated"}` | The cookie is missing or not valid. Run step 1 again and check the user name and the password. |
| A certificate error from curl | The certificate of the host is not trusted by your machine. Add `-k` to test without verification, and see [Troubleshooting](troubleshooting.md#tls-certificate-errors) for the package setting. |

## MKG's own tools and documentation

MKG publishes a Postman collection and a public workspace:

- [MKG API Postman collection page](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/6032/api-postman-collectie)
- [MKG Postman public workspace](https://www.postman.com/mkg-nederland-bv/)
- [MKG API introduction](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/4767/inleiding-tot-de-mkg-api)
- [MKG Getting Started](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/6598/getting-started)
- [Official MKG API call guide](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/10924/hoe-werken-mkg-api-aanroepen)
