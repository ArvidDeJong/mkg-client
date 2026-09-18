# Security policy

This package holds the credentials and the session cookie of an MKG installation, an ERP system with customer, order and article data, so security reports are taken seriously.

## Supported versions

Only the latest minor release of 1.x receives security fixes. Upgrade before reporting.

## What counts as a vulnerability

For example:

- `MKG_PASSWORD`, the `X-CustomerID` value or the `JSESSIONID` cookie ending up in logs, exception messages or output;
- the cached session cookie being readable by other users of the host, or reused across customers;
- TLS verification being skipped while `verify_ssl` is on;
- a request reaching a host other than the configured MKG installation.

## Reporting a vulnerability

Please do **not** open a public issue. Report it privately instead:

- via [GitHub private vulnerability reporting](https://github.com/ArvidDeJong/mkg-client/security/advisories/new), or
- by email to arvid@darvis.nl.

Include the package version, the PHP and Laravel versions and the steps or request that reproduce it.

You will get a reply within a week. Once a fix is released, the advisory is published and you are credited, unless you prefer not to be.

Problems in MKG itself belong with MKG Software; this is an independent package.
