# ArticleService

Class: `Darvis\MkgClient\Services\ArticleService`

## Main methods

- `list(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null): array`
- `findByArticleCode(string $articleCode, array $fieldList = []): array`
- `findArticleRowsByArticleCode(string $articleCode, array $fieldList = []): array`
- `findByArticleName(string $articleName, array $fieldList = [], int $numRows = 25): array`
- `findArticleRowsByArticleName(string $articleName, array $fieldList = [], int $numRows = 25): array`
- `findArticleRowsByCodeOrName(string $search, array $fieldList = [], int $numRows = 25): array`
