# UserService

Class: `Darvis\MkgClient\Services\UserService`

## Main methods

- `list(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null): array`
- `findByUserCode(string $userCode, array $fieldList = []): array`
- `findUserRowsByUserCode(string $userCode, array $fieldList = []): array`
- `findByUserName(string $userName, array $fieldList = [], int $numRows = 25): array`
- `findUserRowsByUserName(string $userName, array $fieldList = [], int $numRows = 25): array`
- `findUserRowsByCodeOrName(string $search, array $fieldList = [], int $numRows = 25): array`
