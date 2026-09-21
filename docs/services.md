---
title: "Service reference"
description: "Every service of darvis/mkg-client with the exact signature of each public method: articles, debtors, contact persons, orders, addresses, relations, users."
nav_order: 4
---

# Service reference

All services live in the namespace `Darvis\MkgClient\Services` and extend `Darvis\MkgClient\BaseMkgService`. Resolve one with `app(DebtorsService::class)` in Laravel, or construct it as shown in [Plain PHP](usage.md#plain-php). Every public method reads; none writes to MKG. The signatures below are generated from the source.

## How to read the method names

| Name | What it returns |
| --- | --- |
| `list…()` | MKG's raw response for the document, with your `fieldList`, `filter`, `numRows` and `sort` |
| `find…()` without `Rows` | MKG's raw response for one lookup |
| `get…ByPrimaryKey()` | MKG's raw response for one record, addressed in the URL. A composite key is written with `+`, for example `1+10001` |
| `find…Rows…()` | The flat rows of a lookup, with values converted to PHP types |
| `extract…Rows()` | The flat, converted rows out of a raw response |
| `getDefault…FieldList()` | The fields the service requests when you pass no `fieldList`; with `databaseOnly: true` only the fields stored in the MKG database |
| `get…FieldMeta()` | The CSV metadata per field: `label`, `type` and `isDatabaseField` |

An empty `fieldList` means the default list, which is very long for orders, addresses and relations; see [Ask only for the fields you need](usage.md#ask-only-for-the-fields-you-need). What a call can throw is listed in [Usage](usage.md#what-a-call-can-throw).

## On every service

```php
getMkgVariantTitle(string $variant): ?string
getMkgVariantTitles(): array
```

`getMkgVariantTitle('vorh')` returns MKG's own Dutch label of a document (`verkooporders`), or `null` for a document the package does not know. `getMkgVariantTitles()` returns all ten labels, keyed by document.

## Articles: `ArticleService`

MKG document: `arti`.

```php
list(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null): array
findByArticleCode(string $articleCode, array $fieldList = []): array
findArticleRowsByArticleCode(string $articleCode, array $fieldList = []): array
findByArticleName(string $articleName, array $fieldList = [], int $numRows = 25): array
findArticleRowsByArticleName(string $articleName, array $fieldList = [], int $numRows = 25): array
findArticleRowsByCodeOrName(string $search, array $fieldList = [], int $numRows = 25): array
getDefaultArticleSearchFieldList(): array
getDefaultArticleFieldList(bool $databaseOnly = false): array
getArticleFieldMeta(): array
extractArticleRows(array $response): array
```

## Debtors: `DebtorsService`

MKG document: `debi`.

```php
list(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null): array
findByDebtorNumber(string|int $debtorNumber, array $fieldList = []): array
findDebtorRowsByDebtorNumber(string|int $debtorNumber, array $fieldList = []): array
findByRelationNumber(string|int $relationNumber, array $fieldList = [], int $numRows = 25): array
findDebtorRowsByRelationNumber(string|int $relationNumber, array $fieldList = [], int $numRows = 25): array
findByDebtorName(string $debtorName, array $fieldList = [], int $numRows = 25): array
findDebtorRowsByDebtorName(string $debtorName, array $fieldList = [], int $numRows = 25): array
findByDebtorEmail(string $debtorEmail, array $fieldList = [], int $numRows = 25): array
findDebtorRowsByDebtorEmail(string $debtorEmail, array $fieldList = [], int $numRows = 25): array
findDebtorRowsByNumberNameOrEmail(string $search, array $fieldList = [], int $numRows = 25): array
findDebtorRowsByNumberOrName(string $search, array $fieldList = [], int $numRows = 25): array
getDefaultDebtorSearchFieldList(): array
getDefaultDebtorFieldList(bool $databaseOnly = false): array
getDebtorFieldMeta(): array
extractDebtorRows(array $response): array
```

## Contact persons: `ContactpersonService`

MKG document: `cprs`.

```php
list(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null): array
findByContactpersonNumber(string|int $contactpersonNumber, array $fieldList = []): array
findContactpersonRowsByContactpersonNumber(string|int $contactpersonNumber, array $fieldList = []): array
findByRelationNumber(string|int $relationNumber, array $fieldList = [], int $numRows = 25): array
findContactpersonRowsByRelationNumber(string|int $relationNumber, array $fieldList = [], int $numRows = 25): array
findByContactpersonName(string $contactpersonName, array $fieldList = [], int $numRows = 25): array
findContactpersonRowsByContactpersonName(string $contactpersonName, array $fieldList = [], int $numRows = 25): array
findByContactpersonEmail(string $contactpersonEmail, array $fieldList = [], int $numRows = 25): array
findContactpersonRowsByContactpersonEmail(string $contactpersonEmail, array $fieldList = [], int $numRows = 25): array
findContactpersonRowsByNumberNameOrEmail(string $search, array $fieldList = [], int $numRows = 25): array
findContactpersonRowsByNumberOrName(string $search, array $fieldList = [], int $numRows = 25): array
getDefaultContactpersonSearchFieldList(): array
getDefaultContactpersonFieldList(bool $databaseOnly = false): array
getContactpersonFieldMeta(): array
extractContactpersonRows(array $response): array
```

## Sales orders: `OrdersService`

MKG documents: `vorh` (order headers), `vorr` (order lines) and `vopa` (order line parameters).

```php
listHeaders(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null, ?int $skipRows = null): array
findHeaderByOrderNumber(string|int $orderNumber, array $fieldList = []): array
findHeaderRowsByOrderNumber(string|int $orderNumber, array $fieldList = []): array
getHeaderByPrimaryKey(string|int $administrationNumber, string|int $orderNumber): array
listRows(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null, ?int $skipRows = null): array
findRowsByOrderNumber(string|int $orderNumber, array $fieldList = []): array
findOrderLineRowsByOrderNumber(string|int $orderNumber, array $fieldList = []): array
getRowByPrimaryKey(string|int $administrationNumber, string|int $orderNumber, string|int $rowNumber): array
listRowParameters(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null, ?int $skipRows = null): array
findRowParametersByOrderNumber(string|int $orderNumber, array $fieldList = []): array
findOrderRowParameterRowsByOrderNumber(string|int $orderNumber, array $fieldList = []): array
extractHeaderRows(array $response): array
extractOrderLineRows(array $response): array
extractOrderRowParameterRows(array $response): array
getDefaultOrderFieldList(bool $databaseOnly = false): array
getDefaultOrderRowFieldList(bool $databaseOnly = false): array
getDefaultOrderRowParameterFieldList(bool $databaseOnly = false): array
getOrderFieldMeta(): array
getOrderRowFieldMeta(): array
getOrderRowParameterFieldMeta(): array
```

## Addresses: `AddressesService`

MKG document: `adrs`. The address document can differ per MKG setup; the `$document` argument names another one. The `adrs` field list and metadata are used either way.

```php
list(array $fieldList = [], ?string $filter = null, ?int $numRows = null, string $document = 'adrs'): array
findByAddressNumber(string|int $addressNumber, array $fieldList = [], string $document = 'adrs'): array
findAddressRowsByAddressNumber(string|int $addressNumber, array $fieldList = [], string $document = 'adrs'): array
getByPrimaryKey(string|int $primaryKey, string $document = 'adrs'): array
extractAddressRows(array $response, string $document = 'adrs'): array
getDefaultAddressFieldList(bool $databaseOnly = false): array
getAddressFieldMeta(): array
```

## Relations: `RelationsService`

MKG document: `rela`. `findByDebtorNumber()` reads `/rela/rela_debi/{debtorNumber}` instead of sending a filter; the source notes that only some MKG setups offer that path.

```php
list(array $fieldList = [], ?string $filter = null, ?int $numRows = null): array
findByRelationNumber(string|int $relationNumber, array $fieldList = []): array
findByDebtorNumber(string|int $debtorNumber, array $fieldList = []): array
getByPrimaryKey(string|int $primaryKey): array
findRelationRowsByRelationNumber(string|int $relationNumber, array $fieldList = []): array
findRelationRowsByDebtorNumber(string|int $debtorNumber, array $fieldList = []): array
extractRelationRows(array $response): array
getDefaultRelationFieldList(bool $databaseOnly = false): array
getRelationFieldMeta(): array
```

## Users: `UserService`

MKG document: `gebr`.

```php
list(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null): array
findByUserCode(string $userCode, array $fieldList = []): array
findUserRowsByUserCode(string $userCode, array $fieldList = []): array
findByUserName(string $userName, array $fieldList = [], int $numRows = 25): array
findUserRowsByUserName(string $userName, array $fieldList = [], int $numRows = 25): array
findUserRowsByCodeOrName(string $search, array $fieldList = [], int $numRows = 25): array
getDefaultUserSearchFieldList(): array
getDefaultUserFieldList(bool $databaseOnly = false): array
getUserFieldMeta(): array
extractUserRows(array $response): array
```
