<?php

namespace Darvis\MkgClient\Services;

use Darvis\MkgClient\BaseMkgService;
use GuzzleHttp\Exception\GuzzleException;

class DebtorsService extends BaseMkgService
{
    /**
     * Default fields for debtor search/sync requests.
     *
     * @var string[]
     */
    public const DEFAULT_DEBTOR_SEARCH_FIELD_LIST = [
        'sys_dat_aanm',
        'sys_tijd_aanm',
        'sys_dat_wijzig',
        'sys_tijd_wijzig',
        'RowTime',
        'RowKey',
        'debi_num',
        'debi_naam',
        'debi_email',
        'debi_actief',
        'debi_blok',
    ];

    /** @var array<string, array{label: string, type: string, isDatabaseField: bool}>|null */
    private static ?array $debtorFieldMeta = null;

    /**
     * @throws GuzzleException
     */
    public function list(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null): array
    {
        $query = [];

        if ($fieldList === []) {
            $fieldList = $this->getDefaultDebtorSearchFieldList();
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

        return $this->get('/debi', $query);
    }

    /**
     * @throws GuzzleException
     */
    public function findByDebtorNumber(string|int $debtorNumber, array $fieldList = []): array
    {
        return $this->list(
            $fieldList,
            $this->buildFilter('debi_num', '=', (string) $debtorNumber),
            1,
            'sys_tijd_wijzig'
        );
    }

    /**
     * Returns only parsed debi rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findDebtorRowsByDebtorNumber(string|int $debtorNumber, array $fieldList = []): array
    {
        return $this->extractDebtorRows(
            $this->findByDebtorNumber($debtorNumber, $fieldList)
        );
    }

    /**
     * @throws GuzzleException
     */
    public function findByRelationNumber(string|int $relationNumber, array $fieldList = [], int $numRows = 25): array
    {
        return $this->list(
            $fieldList,
            $this->buildFilter('rela_num', '=', (string) $relationNumber),
            $numRows,
            'debi_naam'
        );
    }

    /**
     * Returns only parsed debi rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findDebtorRowsByRelationNumber(string|int $relationNumber, array $fieldList = [], int $numRows = 25): array
    {
        return $this->extractDebtorRows(
            $this->findByRelationNumber($relationNumber, $fieldList, $numRows)
        );
    }

    /**
     * @throws GuzzleException
     */
    public function findByDebtorName(string $debtorName, array $fieldList = [], int $numRows = 25): array
    {
        $name = trim($debtorName);

        if ($name == '') {
            return [];
        }

        return $this->list(
            $fieldList,
            $this->buildContainsFilter('debi_naam', $name),
            $numRows,
            'debi_naam'
        );
    }

    /**
     * Returns only parsed debi rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findDebtorRowsByDebtorName(string $debtorName, array $fieldList = [], int $numRows = 25): array
    {
        return $this->extractDebtorRows(
            $this->findByDebtorName($debtorName, $fieldList, $numRows)
        );
    }

    /**
     * @throws GuzzleException
     */
    public function findByDebtorEmail(string $debtorEmail, array $fieldList = [], int $numRows = 25): array
    {
        $email = trim($debtorEmail);

        if ($email == '') {
            return [];
        }

        return $this->list(
            $fieldList,
            $this->buildContainsFilter('debi_email', $email),
            $numRows,
            'debi_email'
        );
    }

    /**
     * Returns only parsed debi rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findDebtorRowsByDebtorEmail(string $debtorEmail, array $fieldList = [], int $numRows = 25): array
    {
        return $this->extractDebtorRows(
            $this->findByDebtorEmail($debtorEmail, $fieldList, $numRows)
        );
    }

    /**
     * Searches by debtor number when input is numeric, otherwise by debtor name.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws GuzzleException
     */
    public function findDebtorRowsByNumberNameOrEmail(string $search, array $fieldList = [], int $numRows = 25): array
    {
        $query = trim($search);

        if ($query == '') {
            return [];
        }

        if (ctype_digit($query)) {
            $rowsByDebtorNumber = $this->findDebtorRowsByDebtorNumber($query, $fieldList);
            $rowsByRelationNumber = $this->findDebtorRowsByRelationNumber($query, $fieldList, $numRows);

            return $this->mergeRowsByRowKey($rowsByDebtorNumber, $rowsByRelationNumber);
        }

        if (str_contains($query, '@')) {
            return $this->findDebtorRowsByDebtorEmail($query, $fieldList, $numRows);
        }

        return $this->findDebtorRowsByDebtorName($query, $fieldList, $numRows);
    }

    /**
     * Backward compatible alias.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws GuzzleException
     */
    public function findDebtorRowsByNumberOrName(string $search, array $fieldList = [], int $numRows = 25): array
    {
        return $this->findDebtorRowsByNumberNameOrEmail($search, $fieldList, $numRows);
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
    public function getDefaultDebtorSearchFieldList(): array
    {
        $meta = $this->getDebtorFieldMeta();

        if ($meta === []) {
            return self::DEFAULT_DEBTOR_SEARCH_FIELD_LIST;
        }

        return array_values(array_filter(
            self::DEFAULT_DEBTOR_SEARCH_FIELD_LIST,
            static fn (string $fieldName): bool => isset($meta[$fieldName])
        ));
    }

    /**
     * @return string[]
     */
    public function getDefaultDebtorFieldList(bool $databaseOnly = false): array
    {
        $meta = $this->getDebtorFieldMeta();

        if ($meta === []) {
            return self::DEFAULT_DEBTOR_SEARCH_FIELD_LIST;
        }

        return $this->extractFieldNames($meta, $databaseOnly);
    }

    /**
     * @return array<string, array{label: string, type: string, isDatabaseField: bool}>
     */
    public function getDebtorFieldMeta(): array
    {
        if (self::$debtorFieldMeta !== null) {
            return self::$debtorFieldMeta;
        }

        self::$debtorFieldMeta = $this->loadFieldMetaFromCsv($this->packageCsvPath('debi'));

        return self::$debtorFieldMeta;
    }

    /**
     * MKG responses are commonly returned as response.ResultData[0].debi.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractDebtorRows(array $response): array
    {
        $rows = $this->extractRowsFromResultData($response, 'debi');

        return $this->normalizeRows($rows, $this->getDebtorFieldMeta());
    }

    private function buildContainsFilter(string $field, string $value): string
    {
        $escaped = str_replace('"', '\\"', trim($value));

        return sprintf('%s contains "%s"', $field, $escaped);
    }
}
