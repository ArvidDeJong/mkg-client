# RelationsService

Class: `Darvis\MkgClient\Services\RelationsService`

Backward-compatible alias:

- `Darvis\MkgClient\Services\RelastionsService` (deprecated)

## Main methods

- `list(array $fieldList = [], ?string $filter = null, ?int $numRows = null): array`
- `findByRelationNumber(string|int $relationNumber, array $fieldList = []): array`
- `findByDebtorNumber(string|int $debtorNumber, array $fieldList = []): array`
- `getByPrimaryKey(string|int $primaryKey): array`
- `findRelationRowsByRelationNumber(string|int $relationNumber, array $fieldList = []): array`
- `findRelationRowsByDebtorNumber(string|int $debtorNumber, array $fieldList = []): array`
