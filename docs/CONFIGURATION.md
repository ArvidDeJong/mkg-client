# Configuration

## Prerequisites

Before configuring the package, ensure your MKG environment is prepared:

- MKG API technical setup is completed (Tomcat/API/SSL)
- MKG Exchange license is active (read-only or CRUD based on use case)
- At least one API application exists in MKG and an API key is generated
- A dedicated MKG user exists with sufficient permissions
- API base URL is reachable without certificate errors

Official MKG setup checklist:

- [MKG Getting Started](https://www.mkg.eu/nl-NL/mijn-mkg/support/kenniscentrum/id/6598/getting-started)

## Environment variables

```dotenv
MKG_URL_AUTH=https://your-mkg-host/restapi/auth
MKG_URL_PROD=https://your-mkg-host/restapi
MKG_CUSTOMER=your-customer-code
MKG_USERNAME=your-api-username
MKG_PASSWORD=your-api-password

# Optional
MKG_VERIFY_SSL=true
MKG_TIMEOUT=30
MKG_CONNECT_TIMEOUT=10
MKG_COOKIE_STORAGE_PATH=mkg/cookie.txt
```

## MKG API v3 examples

Use your own host, API key, and credentials.

On-prem:

```dotenv
MKG_URL_AUTH=https://mkgapi.yourdomain.local:443/mkg/static/auth/j_spring_security_check
MKG_URL_PROD=https://mkgapi.yourdomain.local:443/mkg/web/v3/MKG/Documents
MKG_CUSTOMER=your-api-key
MKG_USERNAME=your-api-user
MKG_PASSWORD=your-api-password
```

MKG Cloud:

```dotenv
MKG_URL_AUTH=https://saasX.mkg.eu:443/mkg/static/auth/j_spring_security_check
MKG_URL_PROD=https://saasX.mkg.eu:443/mkg/web/v3/MKG/Documents
MKG_CUSTOMER=your-api-key
MKG_USERNAME=your-api-user
MKG_PASSWORD=your-api-password
```

Training environment:

```dotenv
MKG_URL_AUTH=https://saasX-oefen.mkg.eu:443/mkgoefenclient/static/auth/j_spring_security_check
MKG_URL_PROD=https://saasX-oefen.mkg.eu:443/mkgoefenclient/web/v3/MKG/Documents
MKG_CUSTOMER=your-api-key
MKG_USERNAME=your-api-user
MKG_PASSWORD=your-api-password
```

## Request model used by this package

- `MKG_URL_AUTH`: login endpoint for MKG form login payload
- `MKG_URL_PROD`: base endpoint for document resources
- Service paths (for example `/debi`, `/arti`, `/vorh`) are appended to `MKG_URL_PROD`
- Requests include `X-CustomerID` and `Accept: application/json`
- `JSESSIONID` is cached and reused until MKG returns `401`
- On `401`, the package clears cookie cache, logs in again, and retries once

## CSV metadata behavior

The package loads metadata CSV files per variant and uses them for default field lists and type normalization.

Lookup order:

1. `storage/mkg/{variant}.csv` (application override)
2. `resources/csv/{variant}.csv` (bundled fallback)
