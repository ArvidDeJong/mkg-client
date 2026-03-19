<?php

namespace Darvis\MkgClient\Services;

use Darvis\MkgClient\BaseMkgService;
use GuzzleHttp\Exception\GuzzleException;

class RelationsService extends BaseMkgService
{
    /**
     * Fallback list when CSV metadata cannot be loaded.
     */
    public const DEFAULT_RELATION_FIELD_LIST = [];

    /** @var array<string, array{label: string, type: string, isDatabaseField: bool}>|null */
    private static ?array $relationFieldMeta = null;

    /**
     * @throws GuzzleException
     */
    public function list(array $fieldList = [], ?string $filter = null, ?int $numRows = null): array
    {
        $query = [];

        if (empty($fieldList)) {
            $fieldList = $this->getDefaultRelationFieldList();
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

        return $this->get('/rela', $query);
    }

    /**
     * @throws GuzzleException
     */
    public function findByRelationNumber(string|int $relationNumber, array $fieldList = []): array
    {
        return $this->list(
            $fieldList,
            $this->buildFilter('rela_num', '=', (string) $relationNumber),
            1
        );
    }

    /**
     * Some MKG setups expose relation lookup by debtor number as /rela/rela_debi/{debi_num}.
     *
     * @throws GuzzleException
     */
    public function findByDebtorNumber(string|int $debtorNumber, array $fieldList = []): array
    {
        $query = [];

        if (empty($fieldList)) {
            $fieldList = $this->getDefaultRelationFieldList();
        }

        if (! empty($fieldList)) {
            $query['FieldList'] = implode(',', $fieldList);
        }

        return $this->get('/rela/rela_debi/'.ltrim((string) $debtorNumber, '/'), $query);
    }

    /**
     * @throws GuzzleException
     */
    public function getByPrimaryKey(string|int $primaryKey): array
    {
        return $this->get('/rela/'.ltrim((string) $primaryKey, '/'));
    }

    /**
     * Returns only parsed relation rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findRelationRowsByRelationNumber(string|int $relationNumber, array $fieldList = []): array
    {
        return $this->extractRelationRows(
            $this->findByRelationNumber($relationNumber, $fieldList)
        );
    }

    /**
     * Returns only parsed relation rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findRelationRowsByDebtorNumber(string|int $debtorNumber, array $fieldList = []): array
    {
        return $this->extractRelationRows(
            $this->findByDebtorNumber($debtorNumber, $fieldList)
        );
    }

    /**
     * Normalizes MKG relation response to a plain row array.
     */
    public function extractRelationRows(array $response): array
    {
        $rows = $this->extractRowsFromResultData($response, 'rela');

        return $this->normalizeRows($rows, $this->getRelationFieldMeta());
    }

    /**
     * Returns a field list derived from resources/csv/rela.csv.
     *
     * @return string[]
     */
    public function getDefaultRelationFieldList(bool $databaseOnly = false): array
    {
        $meta = $this->getRelationFieldMeta();

        if ($meta === []) {
            return self::DEFAULT_RELATION_FIELD_LIST;
        }

        return $this->extractFieldNames($meta, $databaseOnly);
    }

    /**
     * @return array<string, array{label: string, type: string, isDatabaseField: bool}>
     */
    public function getRelationFieldMeta(): array
    {
        if (self::$relationFieldMeta !== null) {
            return self::$relationFieldMeta;
        }

        self::$relationFieldMeta = $this->loadFieldMetaFromCsv($this->packageCsvPath('rela'));

        return self::$relationFieldMeta;
    }
}