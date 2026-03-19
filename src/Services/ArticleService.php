<?php

namespace Darvis\MkgClient\Services;

use Darvis\MkgClient\BaseMkgService;
use GuzzleHttp\Exception\GuzzleException;

class ArticleService extends BaseMkgService
{
    /**
     * Default fields for article search/sync requests.
     *
     * @var string[]
     */
    public const DEFAULT_ARTICLE_SEARCH_FIELD_LIST = [
        'sys_dat_aanm',
        'sys_tijd_aanm',
        'sys_dat_wijzig',
        'sys_tijd_wijzig',
        'RowTime',
        'RowKey',
        'arti_verh_lengte_aantal_grp',
        'arti_vrij_veld_3',
        'arti_memo_verkoop_extern',
        'arti_memo_verkoop_intern',
        'arti_trans_gewicht',
        'arti_trans_eenh',
        'intr_code',
        'arti_vrij_veld_5',
        'arti_eenh_prijs_verkoop',
        'arti_code',
        'admi_num',
        'debi_num',
        'arti_oms_1',
        'arti_oms_2',
        'arti_oms_3',
        'arti_vrij_memo_1_vrij_memo_2_vrij_memo_3',
        'arti_lijst_1',
        'arti_lijst_2',
        'arti_lijst_3',
        'arti_lijst_4',
        'arti_lijst_5',
        'arti_lijst_6',
        'arti_lijst_7',
        'arti_lijst_8',
        'arti_lijst_9',
    ];

    /** @var array<string, array{label: string, type: string, isDatabaseField: bool}>|null */
    private static ?array $articleFieldMeta = null;

    /**
     * @throws GuzzleException
     */
    public function list(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null): array
    {
        $query = [];

        if ($fieldList === []) {
            $fieldList = $this->getDefaultArticleSearchFieldList();
        }

        if (! empty($fieldList)) {
            $query['FieldList'] = implode(',', $fieldList);
        }

        if ($filter) {
            $query['Filter'] = $filter;
        }

        if ($numRows) {
            $query['NumRows'] = $numRows;
        }

        if ($sort) {
            $query['Sort'] = $sort;
        }

        return $this->get('/arti', $query);
    }

    /**
     * @throws GuzzleException
     */
    public function findByArticleCode(string $articleCode, array $fieldList = []): array
    {
        $code = trim($articleCode);

        if ($code == '') {
            return [];
        }

        return $this->list(
            $fieldList,
            $this->buildEqualsTextFilter('arti_code', $code),
            1,
            'sys_tijd_wijzig'
        );
    }

    /**
     * Returns only parsed arti rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findArticleRowsByArticleCode(string $articleCode, array $fieldList = []): array
    {
        return $this->extractArticleRows(
            $this->findByArticleCode($articleCode, $fieldList)
        );
    }

    /**
     * @throws GuzzleException
     */
    public function findByArticleName(string $articleName, array $fieldList = [], int $numRows = 25): array
    {
        $name = trim($articleName);

        if ($name == '') {
            return [];
        }

        return $this->list(
            $fieldList,
            $this->buildContainsFilter('arti_oms_1', $name),
            $numRows,
            'arti_oms_1'
        );
    }

    /**
     * Returns only parsed arti rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findArticleRowsByArticleName(string $articleName, array $fieldList = [], int $numRows = 25): array
    {
        return $this->extractArticleRows(
            $this->findByArticleName($articleName, $fieldList, $numRows)
        );
    }

    /**
     * Searches by exact article code first and merges with name matches.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws GuzzleException
     */
    public function findArticleRowsByCodeOrName(string $search, array $fieldList = [], int $numRows = 25): array
    {
        $query = trim($search);

        if ($query == '') {
            return [];
        }

        $rowsByCode = $this->findArticleRowsByArticleCode($query, $fieldList);
        $rowsByName = $this->findArticleRowsByArticleName($query, $fieldList, $numRows);

        return $this->mergeRowsByArticleCode($rowsByCode, $rowsByName);
    }

    /**
     * @param  array<int, array<string, mixed>>  $first
     * @param  array<int, array<string, mixed>>  $second
     * @return array<int, array<string, mixed>>
     */
    private function mergeRowsByArticleCode(array $first, array $second): array
    {
        $merged = [];
        $seen = [];

        foreach ([$first, $second] as $collection) {
            foreach ($collection as $row) {
                $articleCode = is_scalar($row['arti_code'] ?? null) ? (string) $row['arti_code'] : null;
                $uniqueKey = $articleCode ?: md5(json_encode($row));

                if (isset($seen[$uniqueKey])) {
                    continue;
                }

                $seen[$uniqueKey] = true;
                $merged[] = $row;
            }
        }

        return $merged;
    }

    /**
     * @return string[]
     */
    public function getDefaultArticleSearchFieldList(): array
    {
        return self::DEFAULT_ARTICLE_SEARCH_FIELD_LIST;
    }

    /**
     * @return string[]
     */
    public function getDefaultArticleFieldList(bool $databaseOnly = false): array
    {
        $meta = $this->getArticleFieldMeta();

        if ($meta === []) {
            return self::DEFAULT_ARTICLE_SEARCH_FIELD_LIST;
        }

        return $this->extractFieldNames($meta, $databaseOnly);
    }

    /**
     * @return array<string, array{label: string, type: string, isDatabaseField: bool}>
     */
    public function getArticleFieldMeta(): array
    {
        if (self::$articleFieldMeta !== null) {
            return self::$articleFieldMeta;
        }

        self::$articleFieldMeta = $this->loadFieldMetaFromCsv($this->packageCsvPath('arti'));

        return self::$articleFieldMeta;
    }

    /**
     * MKG responses are commonly returned as response.ResultData[0].arti.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractArticleRows(array $response): array
    {
        $rows = $this->extractRowsFromResultData($response, 'arti');

        return $this->normalizeRows($rows, $this->getArticleFieldMeta());
    }

    private function buildContainsFilter(string $field, string $value): string
    {
        $escaped = str_replace('"', '\\"', trim($value));

        return sprintf('%s contains "%s"', $field, $escaped);
    }

    private function buildEqualsTextFilter(string $field, string $value): string
    {
        $escaped = str_replace('"', '\\"', trim($value));

        return sprintf('%s = "%s"', $field, $escaped);
    }
}
