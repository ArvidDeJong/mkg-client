<?php

namespace Darvis\MkgClient\Services;

use Darvis\MkgClient\BaseMkgService;
use GuzzleHttp\Exception\GuzzleException;

class AddressesService extends BaseMkgService
{
    /**
     * Fallback list when CSV metadata cannot be loaded.
     */
    public const DEFAULT_ADDRESS_FIELD_LIST = [];

    /** @var array<string, array{label: string, type: string, isDatabaseField: bool}>|null */
    private static ?array $addressFieldMeta = null;

    /**
     * This endpoint can differ per MKG inrichting. Adjust $document if needed.
     *
     * @throws GuzzleException
     */
    public function list(array $fieldList = [], ?string $filter = null, ?int $numRows = null, string $document = 'adrs'): array
    {
        $query = [];

        if (empty($fieldList)) {
            $fieldList = $this->getDefaultAddressFieldList();
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

        return $this->get('/'.$document, $query);
    }

    /**
     * @throws GuzzleException
     */
    public function findByAddressNumber(string|int $addressNumber, array $fieldList = [], string $document = 'adrs'): array
    {
        return $this->list(
            $fieldList,
            $this->buildFilter('adrs_num', '=', (string) $addressNumber),
            1,
            $document
        );
    }

    /**
     * Returns only parsed address rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findAddressRowsByAddressNumber(string|int $addressNumber, array $fieldList = [], string $document = 'adrs'): array
    {
        return $this->extractAddressRows(
            $this->findByAddressNumber($addressNumber, $fieldList, $document),
            $document
        );
    }

    /**
     * @throws GuzzleException
     */
    public function getByPrimaryKey(string|int $primaryKey, string $document = 'adrs'): array
    {
        return $this->get('/'.$document.'/'.ltrim((string) $primaryKey, '/'));
    }

    /**
    * Normalizes MKG address response to a plain row array.
     */
    public function extractAddressRows(array $response, string $document = 'adrs'): array
    {
        $rows = $this->extractRowsFromResultData($response, $document);

        return $this->normalizeRows($rows, $this->getAddressFieldMeta());
    }

    /**
     * Returns a field list derived from resources/csv/adrs.csv.
     *
     * @return string[]
     */
    public function getDefaultAddressFieldList(bool $databaseOnly = false): array
    {
        $meta = $this->getAddressFieldMeta();

        if ($meta === []) {
            return self::DEFAULT_ADDRESS_FIELD_LIST;
        }

        return $this->extractFieldNames($meta, $databaseOnly);
    }

    /**
     * @return array<string, array{label: string, type: string, isDatabaseField: bool}>
     */
    public function getAddressFieldMeta(): array
    {
        if (self::$addressFieldMeta !== null) {
            return self::$addressFieldMeta;
        }

        self::$addressFieldMeta = $this->loadFieldMetaFromCsv($this->packageCsvPath('adrs'));

        return self::$addressFieldMeta;
    }
}
