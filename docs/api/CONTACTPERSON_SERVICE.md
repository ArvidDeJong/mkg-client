# ContactpersonService

Class: `Darvis\MkgClient\Services\ContactpersonService`

## Main methods

- `list(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null): array`
- `findByContactpersonNumber(string|int $contactpersonNumber, array $fieldList = []): array`
- `findContactpersonRowsByContactpersonNumber(string|int $contactpersonNumber, array $fieldList = []): array`
- `findByRelationNumber(string|int $relationNumber, array $fieldList = [], int $numRows = 25): array`
- `findContactpersonRowsByRelationNumber(string|int $relationNumber, array $fieldList = [], int $numRows = 25): array`
- `findByContactpersonName(string $contactpersonName, array $fieldList = [], int $numRows = 25): array`
- `findContactpersonRowsByContactpersonName(string $contactpersonName, array $fieldList = [], int $numRows = 25): array`
- `findByContactpersonEmail(string $contactpersonEmail, array $fieldList = [], int $numRows = 25): array`
- `findContactpersonRowsByContactpersonEmail(string $contactpersonEmail, array $fieldList = [], int $numRows = 25): array`
- `findContactpersonRowsByNumberNameOrEmail(string $search, array $fieldList = [], int $numRows = 25): array`
