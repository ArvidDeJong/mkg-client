# AddressesService

Class: `Darvis\MkgClient\Services\AddressesService`

## Main methods

- `list(array $fieldList = [], ?string $filter = null, ?int $numRows = null, string $document = 'adrs'): array`
- `findByAddressNumber(string|int $addressNumber, array $fieldList = [], string $document = 'adrs'): array`
- `findAddressRowsByAddressNumber(string|int $addressNumber, array $fieldList = [], string $document = 'adrs'): array`
- `getByPrimaryKey(string|int $primaryKey, string $document = 'adrs'): array`
