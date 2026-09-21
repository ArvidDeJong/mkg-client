<?php

namespace Darvis\MkgClient\Services;

use Darvis\MkgClient\BaseMkgService;
use GuzzleHttp\Exception\GuzzleException;

class OrdersService extends BaseMkgService
{
    /**
     * Fallback list when CSV metadata cannot be loaded.
     */
    public const DEFAULT_ORDER_FIELD_LIST = [];

    /** @var array<string, array{label: string, type: string, isDatabaseField: bool}>|null */
    private static ?array $orderFieldMeta = null;

    /** @var array<string, array{label: string, type: string, isDatabaseField: bool}>|null */
    private static ?array $orderRowFieldMeta = null;

    /** @var array<string, array{label: string, type: string, isDatabaseField: bool}>|null */
    private static ?array $orderRowParameterFieldMeta = null;

    /**
     * Verkooporder headers (vorh).
     *
     * @throws GuzzleException
     */
    public function listHeaders(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null, ?int $skipRows = null): array
    {
        return $this->listDocument('vorh', $fieldList, $this->getDefaultOrderFieldList(), $filter, $numRows, $sort, $skipRows);
    }

    /**
     * @throws GuzzleException
     */
    public function findHeaderByOrderNumber(string|int $orderNumber, array $fieldList = []): array
    {
        return $this->listHeaders(
            $fieldList,
            $this->buildFilter('vorh_num', '=', (string) $orderNumber),
            1
        );
    }

    /**
     * Returns only parsed vorh rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findHeaderRowsByOrderNumber(string|int $orderNumber, array $fieldList = []): array
    {
        return $this->extractHeaderRows(
            $this->findHeaderByOrderNumber($orderNumber, $fieldList)
        );
    }

    /**
     * @throws GuzzleException
     */
    public function getHeaderByPrimaryKey(string|int $administrationNumber, string|int $orderNumber): array
    {
        return $this->get('/vorh/'.$this->encodePathSegment($administrationNumber).'+'.$this->encodePathSegment($orderNumber));
    }

    /**
     * Verkooporder regels (vorr).
     *
     * @throws GuzzleException
     */
    public function listRows(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null, ?int $skipRows = null): array
    {
        return $this->listDocument('vorr', $fieldList, $this->getDefaultOrderRowFieldList(), $filter, $numRows, $sort, $skipRows);
    }

    /**
     * @throws GuzzleException
     */
    public function findRowsByOrderNumber(string|int $orderNumber, array $fieldList = []): array
    {
        return $this->listRows(
            $fieldList,
            $this->buildFilter('vorh_num', '=', (string) $orderNumber)
        );
    }

    /**
     * Returns only parsed vorr rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findOrderLineRowsByOrderNumber(string|int $orderNumber, array $fieldList = []): array
    {
        return $this->extractOrderLineRows(
            $this->findRowsByOrderNumber($orderNumber, $fieldList)
        );
    }

    /**
     * @throws GuzzleException
     */
    public function getRowByPrimaryKey(string|int $administrationNumber, string|int $orderNumber, string|int $rowNumber): array
    {
        return $this->get(
            '/vorr/'.$this->encodePathSegment($administrationNumber)
            .'+'.$this->encodePathSegment($orderNumber)
            .'+'.$this->encodePathSegment($rowNumber)
        );
    }

    /**
     * Verkooporder regel parameters (vopa).
     *
     * @throws GuzzleException
     */
    public function listRowParameters(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null, ?int $skipRows = null): array
    {
        return $this->listDocument('vopa', $fieldList, $this->getDefaultOrderRowParameterFieldList(), $filter, $numRows, $sort, $skipRows);
    }

    /**
     * @throws GuzzleException
     */
    public function findRowParametersByOrderNumber(string|int $orderNumber, array $fieldList = []): array
    {
        return $this->listRowParameters(
            $fieldList,
            $this->buildFilter('vorh_num', '=', (string) $orderNumber)
        );
    }

    /**
     * Returns only parsed vopa rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findOrderRowParameterRowsByOrderNumber(string|int $orderNumber, array $fieldList = []): array
    {
        return $this->extractOrderRowParameterRows(
            $this->findRowParametersByOrderNumber($orderNumber, $fieldList)
        );
    }

    /**
     * Normalizes MKG vorh response to a plain row array.
     */
    public function extractHeaderRows(array $response): array
    {
        return $this->extractNormalizedRows($response, 'vorh', $this->getOrderFieldMeta());
    }

    /**
     * Normalizes MKG vorr response to a plain row array.
     */
    public function extractOrderLineRows(array $response): array
    {
        return $this->extractNormalizedRows($response, 'vorr', $this->getOrderRowFieldMeta());
    }

    /**
     * Normalizes MKG vopa response to a plain row array.
     */
    public function extractOrderRowParameterRows(array $response): array
    {
        return $this->extractNormalizedRows($response, 'vopa', $this->getOrderRowParameterFieldMeta());
    }

    /**
     * Returns a field list derived from resources/csv/vorh.csv.
     *
     * @return string[]
     */
    public function getDefaultOrderFieldList(bool $databaseOnly = false): array
    {
        $meta = $this->getOrderFieldMeta();

        if ($meta === []) {
            return self::DEFAULT_ORDER_FIELD_LIST;
        }

        return $this->extractFieldNames($meta, $databaseOnly);
    }

    /**
     * Returns a field list derived from resources/csv/vorr.csv.
     *
     * @return string[]
     */
    public function getDefaultOrderRowFieldList(bool $databaseOnly = false): array
    {
        $meta = $this->getOrderRowFieldMeta();

        if ($meta === []) {
            return self::DEFAULT_ORDER_FIELD_LIST;
        }

        return $this->extractFieldNames($meta, $databaseOnly);
    }

    /**
     * Returns a field list derived from resources/csv/vopa.csv.
     *
     * @return string[]
     */
    public function getDefaultOrderRowParameterFieldList(bool $databaseOnly = false): array
    {
        $meta = $this->getOrderRowParameterFieldMeta();

        if ($meta === []) {
            return self::DEFAULT_ORDER_FIELD_LIST;
        }

        return $this->extractFieldNames($meta, $databaseOnly);
    }

    /**
     * @return array<string, array{label: string, type: string, isDatabaseField: bool}>
     */
    public function getOrderFieldMeta(): array
    {
        return $this->getCachedFieldMeta(self::$orderFieldMeta, 'vorh');
    }

    /**
     * @return array<string, array{label: string, type: string, isDatabaseField: bool}>
     */
    public function getOrderRowFieldMeta(): array
    {
        return $this->getCachedFieldMeta(self::$orderRowFieldMeta, 'vorr');
    }

    /**
     * @return array<string, array{label: string, type: string, isDatabaseField: bool}>
     */
    public function getOrderRowParameterFieldMeta(): array
    {
        return $this->getCachedFieldMeta(self::$orderRowParameterFieldMeta, 'vopa');
    }
}
