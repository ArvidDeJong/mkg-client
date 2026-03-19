# DebtorsService

Class: `Darvis\MkgClient\Services\DebtorsService`

## Main methods

- `list(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null): array`
- `findByDebtorNumber(string|int $debtorNumber, array $fieldList = []): array`
- `findDebtorRowsByDebtorNumber(string|int $debtorNumber, array $fieldList = []): array`
- `findByRelationNumber(string|int $relationNumber, array $fieldList = [], int $numRows = 25): array`
- `findDebtorRowsByRelationNumber(string|int $relationNumber, array $fieldList = [], int $numRows = 25): array`
- `findByDebtorName(string $debtorName, array $fieldList = [], int $numRows = 25): array`
- `findDebtorRowsByDebtorName(string $debtorName, array $fieldList = [], int $numRows = 25): array`
- `findByDebtorEmail(string $debtorEmail, array $fieldList = [], int $numRows = 25): array`
- `findDebtorRowsByDebtorEmail(string $debtorEmail, array $fieldList = [], int $numRows = 25): array`
- `findDebtorRowsByNumberNameOrEmail(string $search, array $fieldList = [], int $numRows = 25): array`
