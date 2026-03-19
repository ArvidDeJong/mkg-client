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
        return $this->listDocument('debi', $fieldList, $this->getDefaultDebtorSearchFieldList(), $filter, $numRows, $sort);
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
            $this->buildContainsTextFilter('debi_naam', $name),
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
            $this->buildContainsTextFilter('debi_email', $email),
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

            return $this->mergeUniqueRows($rowsByDebtorNumber, $rowsByRelationNumber, 'RowKey');
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
     * @return string[]
     */
    public function getDefaultDebtorSearchFieldList(): array
    {
        return $this->filterAvailableFieldList(self::DEFAULT_DEBTOR_SEARCH_FIELD_LIST, $this->getDebtorFieldMeta());
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
        return $this->getCachedFieldMeta(self::$debtorFieldMeta, 'debi');
    }

    /**
     * MKG responses are commonly returned as response.ResultData[0].debi.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractDebtorRows(array $response): array
    {
        return $this->extractNormalizedRows($response, 'debi', $this->getDebtorFieldMeta());
    }
}
