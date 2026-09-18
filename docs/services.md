---
title: Service reference
description: "Every service of darvis/mkg-client with its public methods: articles, debtors, contact persons, sales orders, addresses, relations and users, plus what all services share."
nav_order: 4
---

# Service reference

All services live under `Darvis\MkgClient\Services` and extend `Darvis\MkgClient\BaseMkgService`. They share the login and session handling, the CSV field metadata, the extraction of rows from `response.ResultData` and the type normalisation; see [Usage](usage.md) for how the pieces fit together.

Three kinds of methods recur in every service. `list…()` and `find…()` return MKG's raw response. `find…Rows…()` and `extract…Rows()` return the flat, normalised rows. `getDefault…FieldList()` and `get…FieldMeta()` expose the CSV metadata the service works with. The method signatures below are generated from the source.


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

MKG documents: `vorh, vorr, vopa`.

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

MKG document: `adrs`.

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

MKG document: `rela`.

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
