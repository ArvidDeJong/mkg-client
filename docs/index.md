---
title: "Home"
nav_order: 1
description: "darvis/mkg-client reads debtors, articles and sales orders from the MKG Software ERP REST API in PHP or Laravel, with login, session and typed rows handled."
permalink: /
---

# darvis/mkg-client

A PHP client that reads data from the REST API of [MKG Software](https://www.mkg.eu), a Dutch ERP system (the software a manufacturer runs its orders, stock and customers in). It logs in, keeps the session, and gives you one service class per MKG document, so you call `findDebtorRowsByDebtorNumber(10001)` instead of building URLs and query strings.

This is an independent open-source package, not affiliated with MKG Software.

## Who it is for

Developers who connect a PHP or Laravel application to an MKG installation: a customer portal that shows orders, a sync of debtors or articles, a lookup in a back office.

## What it does not do

- It does not write to MKG. Every public method reads.
- It does not page for you. MKG returns at most 1000 rows per call; [Usage](usage.md#read-more-than-1000-rows) shows the loop.
- It does not cache results or map rows to models. You get arrays.
- It does not use Laravel's HTTP client, so `Http::fake()` and Debugbar's HTTP collector do not see its traffic. See [Testing](testing.md).

## Requirements

- PHP 8.2 or higher
- An MKG installation with the API switched on, and API credentials for it (see [Installation](installation.md#what-you-need-from-mkg))
- Laravel 11, 12 or 13, only when you use the Laravel integration; the client also works in plain PHP

## Install

```bash
composer require darvis/mkg-client
```

Then set `MKG_HOST`, `MKG_CUSTOMER`, `MKG_USERNAME` and `MKG_PASSWORD` in `.env`, and [check that it works](installation.md#check-that-it-works).

## What you get

- **Login and session handled for you**: the form login, the `JSESSIONID` cookie, the `X-CustomerID` header, and one automatic new login when MKG answers `401`.
- **Derived URLs**: set the host and the client builds the REST base and the login URL.
- **A service per document**: articles (`arti`), debtors (`debi`), contact persons (`cprs`), sales orders (`vorh`, `vorr`, `vopa`), addresses (`adrs`), relations (`rela`) and users (`gebr`).
- **Typed rows**: the field types from MKG's own CSV export turn integers, amounts, booleans and dates into PHP values. Not every type is converted; see [Usage](usage.md#which-values-are-converted).
- **Safe lookups**: the finders quote and escape their value, and URL-encode primary keys.
- **Errors you can act on**: a `403` is explained as a wrong base URL, and a redirect or a login page throws instead of looking like "no rows".
- **Request logging**: every call with method, path, status and duration, and a warning for a slow call.

## Pages

- [Installation & configuration](installation.md): what you need from MKG, the steps, every setting, and a check that it works
- [Usage](usage.md): one complete example, rows versus the raw response, filters, paging, errors, plain PHP and request logging
- [Service reference](services.md): every service and the signature of every public method
- [Testing](testing.md): test your own code with a Guzzle `MockHandler`, without an MKG installation
- [Verification](verification.md): check the URL and the credentials with curl before you write code
- [Troubleshooting](troubleshooting.md): every exception message and log line, with cause and fix
- [FAQ](faq.md): short answers about the package and the MKG API
