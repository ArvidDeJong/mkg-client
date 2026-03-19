# OrdersService

Class: `Darvis\MkgClient\Services\OrdersService`

## Header methods (`vorh`)

- `listHeaders(array $fieldList = [], ?string $filter = null, ?int $numRows = null): array`
- `findHeaderByOrderNumber(string|int $orderNumber, array $fieldList = []): array`
- `findHeaderRowsByOrderNumber(string|int $orderNumber, array $fieldList = []): array`
- `getHeaderByPrimaryKey(string|int $administrationNumber, string|int $orderNumber): array`

## Line methods (`vorr`)

- `listRows(array $fieldList = [], ?string $filter = null, ?int $numRows = null): array`
- `findRowsByOrderNumber(string|int $orderNumber, array $fieldList = []): array`
- `findOrderLineRowsByOrderNumber(string|int $orderNumber, array $fieldList = []): array`
- `getRowByPrimaryKey(string|int $administrationNumber, string|int $orderNumber, string|int $rowNumber): array`

## Parameter methods (`vopa`)

- `listRowParameters(array $fieldList = [], ?string $filter = null, ?int $numRows = null): array`
- `findRowParametersByOrderNumber(string|int $orderNumber, array $fieldList = []): array`
- `findOrderRowParameterRowsByOrderNumber(string|int $orderNumber, array $fieldList = []): array`
