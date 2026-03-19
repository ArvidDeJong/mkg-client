<?php

namespace Darvis\MkgClient\Services;

use Darvis\MkgClient\BaseMkgService;
use GuzzleHttp\Exception\GuzzleException;

class UserService extends BaseMkgService
{
    /**
     * Default fields for user search/sync requests.
     *
     * @var string[]
     */
    public const DEFAULT_USER_SEARCH_FIELD_LIST = [
        'sys_dat_aanm',
        'sys_tijd_aanm',
        'sys_dat_wijzig',
        'sys_tijd_wijzig',
        'RowTime',
        'RowKey',
        'gebr_code',
        'gebr_naam',
        'gebr_actief',
        'gebr_beheerder',
    ];

    /** @var array<string, array{label: string, type: string, isDatabaseField: bool}>|null */
    private static ?array $userFieldMeta = null;

    /**
     * @throws GuzzleException
     */
    public function list(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null): array
    {
        $query = [];

        if ($fieldList === []) {
            $fieldList = $this->getDefaultUserSearchFieldList();
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

        return $this->get('/gebr', $query);
    }

    /**
     * @throws GuzzleException
     */
    public function findByUserCode(string $userCode, array $fieldList = []): array
    {
        $code = trim($userCode);

        if ($code == '') {
            return [];
        }

        return $this->list(
            $fieldList,
            $this->buildEqualsTextFilter('gebr_code', $code),
            1,
            'gebr_code'
        );
    }

    /**
     * Returns only parsed gebr rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findUserRowsByUserCode(string $userCode, array $fieldList = []): array
    {
        return $this->extractUserRows(
            $this->findByUserCode($userCode, $fieldList)
        );
    }

    /**
     * @throws GuzzleException
     */
    public function findByUserName(string $userName, array $fieldList = [], int $numRows = 25): array
    {
        $name = trim($userName);

        if ($name == '') {
            return [];
        }

        return $this->list(
            $fieldList,
            $this->buildContainsFilter('gebr_naam', $name),
            $numRows,
            'gebr_naam'
        );
    }

    /**
     * Returns only parsed gebr rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findUserRowsByUserName(string $userName, array $fieldList = [], int $numRows = 25): array
    {
        return $this->extractUserRows(
            $this->findByUserName($userName, $fieldList, $numRows)
        );
    }

    /**
     * Searches by user code first and merges with name matches.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws GuzzleException
     */
    public function findUserRowsByCodeOrName(string $search, array $fieldList = [], int $numRows = 25): array
    {
        $query = trim($search);

        if ($query == '') {
            return [];
        }

        $rowsByCode = $this->findUserRowsByUserCode($query, $fieldList);
        $rowsByName = $this->findUserRowsByUserName($query, $fieldList, $numRows);

        return $this->mergeRowsByRowKey($rowsByCode, $rowsByName);
    }

    /**
     * @param  array<int, array<string, mixed>>  $first
     * @param  array<int, array<string, mixed>>  $second
     * @return array<int, array<string, mixed>>
     */
    private function mergeRowsByRowKey(array $first, array $second): array
    {
        $merged = [];
        $seen = [];

        foreach ([$first, $second] as $collection) {
            foreach ($collection as $row) {
                $rowKey = is_string($row['RowKey'] ?? null) ? $row['RowKey'] : null;
                $uniqueKey = $rowKey ?? md5(json_encode($row));

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
    public function getDefaultUserSearchFieldList(): array
    {
        $meta = $this->getUserFieldMeta();

        if ($meta === []) {
            return self::DEFAULT_USER_SEARCH_FIELD_LIST;
        }

        return array_values(array_filter(
            self::DEFAULT_USER_SEARCH_FIELD_LIST,
            static fn (string $fieldName): bool => isset($meta[$fieldName])
        ));
    }

    /**
     * @return string[]
     */
    public function getDefaultUserFieldList(bool $databaseOnly = false): array
    {
        $meta = $this->getUserFieldMeta();

        if ($meta === []) {
            return self::DEFAULT_USER_SEARCH_FIELD_LIST;
        }

        return $this->extractFieldNames($meta, $databaseOnly);
    }

    /**
     * @return array<string, array{label: string, type: string, isDatabaseField: bool}>
     */
    public function getUserFieldMeta(): array
    {
        if (self::$userFieldMeta !== null) {
            return self::$userFieldMeta;
        }

        self::$userFieldMeta = $this->loadFieldMetaFromCsv($this->packageCsvPath('gebr'));

        return self::$userFieldMeta;
    }

    /**
     * MKG responses are commonly returned as response.ResultData[0].gebr.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractUserRows(array $response): array
    {
        return $this->extractRowsFromResultData($response, 'gebr');
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
