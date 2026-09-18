---
title: Home
nav_order: 1
description: "darvis/mkg-client: a PHP client for the MKG Software REST API that handles the Tomcat form login and the JSESSIONID session and offers a typed service per MKG document, with Laravel integration."
permalink: /
---

# darvis/mkg-client

A PHP client for the REST API of [MKG Software](https://www.mkg.eu), the Dutch ERP system. It handles the Tomcat form login and the `JSESSIONID` session for you, and gives you a typed service layer over the MKG documents instead of hand-built URLs. Framework-agnostic, with a Laravel service provider that registers itself.

This is an independent open-source package, not affiliated with MKG Software.

```bash
composer require darvis/mkg-client
```

Requires PHP 8.2+ and valid MKG API credentials. The Laravel integration works on Laravel 11, 12 and 13.

## Features

- **Login and session handled for you**: the form login, the `JSESSIONID` cookie, the `X-CustomerID` header, and one automatic re-login when MKG answers `401`
- **Derived URLs**: set the host and the client builds the REST base and the login URL, so nobody types a retired path
- **A typed service per document**: articles, debtors, contact persons, sales orders (headers, lines, parameters), addresses, relations and users
- **Field metadata from CSV**: default field lists and type normalisation (dates, numbers, booleans) per document, overridable per application
- **Clear errors**: a `403` is explained as a wrong base URL, not retried as a session problem
- **Request logging**: every call with method, path, status and duration, and a warning for slow calls, because Laravel's HTTP client profilers never see plain Guzzle traffic
- **Plain PHP or Laravel**: config providers and cookie stores for both, and a Laravel Boost guideline for AI tooling in your app

## Quick example

```php
use Darvis\MkgClient\Services\DebtorsService;
use Darvis\MkgClient\Services\OrdersService;

$rows = app(DebtorsService::class)->findDebtorRowsByNumberNameOrEmail('10001');

$headers = app(OrdersService::class)->findHeaderRowsByOrderNumber('500123');
$lines = app(OrdersService::class)->findOrderLineRowsByOrderNumber('500123');
```

## Read next

- [Installation & configuration](installation.md): requirements, the MKG side, environment variables and every config key
- [Usage](usage.md): Laravel and plain PHP, filters, paging, sorting and request logging
- [Service reference](services.md): every service and its methods
- [Verification](verification.md): check connectivity and credentials with curl or Postman before writing code
- [Troubleshooting](troubleshooting.md): 401 versus 403, stalls, missing rows, config and TLS
- [FAQ](faq.md): the MKG API questions that are hard to find elsewhere
