<?php

namespace Darvis\MkgClient\Services;

use Darvis\MkgClient\BaseMkgService;
use GuzzleHttp\Exception\GuzzleException;

class PackageListsService extends BaseMkgService
{
    /**
     * Sent when the caller passes no field list for the headers.
     *
     * A package list (pakbon) has no sales order field of its own: `vorh_num` lives on its rows,
     * because one package list can ship lines of several sales orders.
     */
    public const DEFAULT_PACKAGE_LIST_FIELD_LIST = [
        'pkbh_num',
        'admi_num',
        'debi_num',
        'pkbh_debi_naam_1',
        'pkbh_dat_pakbon',
        'pkbh_dat_aflever',
        'pkbh_colli_aantal',
        'pkbh_storno',
        'sys_dat_wijzig',
        'sys_tijd_wijzig',
    ];

    /**
     * Sent when the caller passes no field list for the rows.
     *
     * Without a `FieldList` MKG answers with the key fields only (`admi_num`, `pkbh_num`,
     * `pkbr_num`), so a row without this list has no article, description or quantity.
     * `pkbr_aantal_verkoop` is the ordered quantity, `pkbr_aantal_pakbon` what this package list
     * ships and `pkbr_aantal_backorder` what is still to come.
     */
    public const DEFAULT_PACKAGE_LIST_ROW_FIELD_LIST = [
        'pkbh_num',
        'pkbr_num',
        'vorh_num',
        'vorr_num',
        'arti_code',
        'pkbr_oms_1',
        'pkbr_oms_2',
        'pkbr_oms_3',
        'pkbr_aantal_verkoop',
        'pkbr_aantal_pakbon',
        'pkbr_aantal_backorder',
        'pkbr_eenh_order',
        'pkbr_referentie',
        'sys_dat_wijzig',
        'sys_tijd_wijzig',
    ];

    /**
     * The most rows MKG returns in one call. The row finders ask for it, because MKG returns 100
     * rows without `NumRows` and gives no sign that more exist.
     */
    private const MAX_ROWS_PER_CALL = 1000;

    /**
     * Package list headers (pkbh).
     *
     * @param  string[]  $fieldList
     *
     * @throws GuzzleException
     */
    public function listHeaders(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null, ?int $skipRows = null): array
    {
        return $this->listDocument('pkbh', $fieldList, self::DEFAULT_PACKAGE_LIST_FIELD_LIST, $filter, $numRows, $sort, $skipRows);
    }

    /**
     * @param  string[]  $fieldList
     *
     * @throws GuzzleException
     */
    public function findHeaderByPackageListNumber(string|int $packageListNumber, array $fieldList = []): array
    {
        return $this->listHeaders(
            $fieldList,
            $this->buildFilter('pkbh_num', '=', (string) $packageListNumber),
            1
        );
    }

    /**
     * Returns only the pkbh rows from the MKG response.
     *
     * @param  string[]  $fieldList
     * @return array<int, array<string, mixed>>
     *
     * @throws GuzzleException
     */
    public function findHeaderRowsByPackageListNumber(string|int $packageListNumber, array $fieldList = []): array
    {
        return $this->extractHeaderRows(
            $this->findHeaderByPackageListNumber($packageListNumber, $fieldList)
        );
    }

    /**
     * Turns an MKG pkbh response into a plain row array.
     *
     * The package ships no field metadata for pkbh, so the values stay as MKG sent them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractHeaderRows(array $response): array
    {
        return $this->extractRowsFromResultData($response, 'pkbh');
    }

    /**
     * Package list rows (pkbr).
     *
     * @param  string[]  $fieldList
     *
     * @throws GuzzleException
     */
    public function listRows(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null, ?int $skipRows = null): array
    {
        return $this->listDocument('pkbr', $fieldList, self::DEFAULT_PACKAGE_LIST_ROW_FIELD_LIST, $filter, $numRows, $sort, $skipRows);
    }

    /**
     * The rows of one package list, in row number order, up to 1000 rows.
     *
     * @param  string[]  $fieldList
     *
     * @throws GuzzleException
     */
    public function findRowsByPackageListNumber(string|int $packageListNumber, array $fieldList = []): array
    {
        return $this->listRows(
            $fieldList,
            $this->buildFilter('pkbh_num', '=', (string) $packageListNumber),
            self::MAX_ROWS_PER_CALL,
            'pkbr_num'
        );
    }

    /**
     * Returns only the pkbr rows of one package list.
     *
     * @param  string[]  $fieldList
     * @return array<int, array<string, mixed>>
     *
     * @throws GuzzleException
     */
    public function findLineRowsByPackageListNumber(string|int $packageListNumber, array $fieldList = []): array
    {
        return $this->extractLineRows(
            $this->findRowsByPackageListNumber($packageListNumber, $fieldList)
        );
    }

    /**
     * The package list rows of one sales order, across all its package lists, up to 1000 rows.
     *
     * @param  string[]  $fieldList
     *
     * @throws GuzzleException
     */
    public function findRowsBySalesOrderNumber(string|int $salesOrderNumber, array $fieldList = []): array
    {
        return $this->listRows(
            $fieldList,
            $this->buildFilter('vorh_num', '=', (string) $salesOrderNumber),
            self::MAX_ROWS_PER_CALL,
            'pkbh_num'
        );
    }

    /**
     * Returns only the pkbr rows of one sales order.
     *
     * @param  string[]  $fieldList
     * @return array<int, array<string, mixed>>
     *
     * @throws GuzzleException
     */
    public function findLineRowsBySalesOrderNumber(string|int $salesOrderNumber, array $fieldList = []): array
    {
        return $this->extractLineRows(
            $this->findRowsBySalesOrderNumber($salesOrderNumber, $fieldList)
        );
    }

    /**
     * Turns an MKG pkbr response into a plain row array.
     *
     * The package ships no field metadata for pkbr, so the values stay as MKG sent them: the
     * quantities are floats and `pkbr_num` is an integer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractLineRows(array $response): array
    {
        return $this->extractRowsFromResultData($response, 'pkbr');
    }
}
