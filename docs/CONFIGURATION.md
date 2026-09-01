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
MKG_HOST=your-mkg-host
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

Only two things vary between installations: the host (optionally with a port)
and the client segment. Set those and the client builds both URLs itself.

On-prem:

```dotenv
MKG_HOST=mkgapi.yourdomain.local:443
```

MKG Cloud:

```dotenv
MKG_HOST=saasX.mkg.eu:443
```

Training environment (note the client segment):

```dotenv
MKG_HOST=saasX-oefen.mkg.eu:443
MKG_CLIENT_PATH=mkgoefenclient
```

Each of these resolves to:

| | |
| --- | --- |
| REST base | `https://{host}/{client_path}/web/v3/MKG/Documents` |
| Authentication | `https://{host}/{client_path}/static/auth/j_spring_security_check` |

Keep those paths out of your environment file. They belong to the MKG API
version, not to your environment, and a typo there is answered with a `403` and a
Tomcat HTML error page that reads like a permission problem. The retired
`/mkg/rest/v1` and the plausible-looking `/mkg/rest/v3` both fail that way.

`MKG_URL_AUTH` and `MKG_URL_PROD` still override the derived values, for an
installation that deviates from this layout.

## Request model used by this package

- `MKG_HOST`: hostname of the installation, optionally with a port
- `MKG_CLIENT_PATH`: client segment, `mkg` by default
- `MKG_URL_AUTH` / `MKG_URL_PROD`: optional explicit overrides
- Service paths (for example `/debi`, `/arti`, `/vorh`) are appended to the REST base
- Requests include `X-CustomerID` and `Accept: application/json`
- `JSESSIONID` is cached and reused until MKG returns `401`
- On `401`, the package clears cookie cache, logs in again, and retries once

## CSV metadata behavior

The package loads metadata CSV files per variant and uses them for default field lists and type normalization.

Lookup order:

1. `storage/mkg/{variant}.csv` (application override)
2. `resources/csv/{variant}.csv` (bundled fallback)
