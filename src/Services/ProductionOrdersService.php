<?php

namespace Darvis\MkgClient\Services;

use Darvis\MkgClient\BaseMkgService;
use GuzzleHttp\Exception\GuzzleException;

class ProductionOrdersService extends BaseMkgService
{
    /**
     * Sent when the caller passes no field list.
     *
     * `t_vorh_num` (the linked sales order) and `t_klant` (the customer name) are calculated
     * fields: MKG leaves them out of an answer, also with `FieldList=*`, unless they are asked for
     * by name. A production order for stock has an empty `t_vorh_num`.
     */
    public const DEFAULT_PRODUCTION_ORDER_FIELD_LIST = [
        'prdh_num',
        't_vorh_num',
        't_klant',
        'sys_dat_wijzig',
        'sys_tijd_wijzig',
    ];

    /**
     * Sent when the caller passes no field list for the rows.
     *
     * `prdr_oms_1` is the description of the article. On a production order for stock
     * `prdr_oms_2` often holds the color; on a sales order line that is `vorr_oms_3`.
     */
    public const DEFAULT_PRODUCTION_ORDER_ROW_FIELD_LIST = [
        'prdh_num',
        'prdr_num',
        'prdr_volgorde',
        'arti_code',
        'prdr_oms_1',
        'prdr_oms_2',
        'prdr_oms_3',
        'prdr_aantal',
        'eenh_code',
        'sys_dat_wijzig',
        'sys_tijd_wijzig',
    ];

    /**
     * The most rows MKG returns in one call. The row finders ask for it, because MKG returns 100
     * rows without `NumRows` and gives no sign that more exist.
     */
    private const MAX_ROWS_PER_CALL = 1000;

    /**
     * Production order headers (prdh).
     *
     * MKG does not filter on the calculated field `t_vorh_num`. Filter on a database field, such
     * as `sys_dat_wijzig` or `prdh_num`, and read the linked sales order from the rows.
     *
     * @param  string[]  $fieldList
     *
     * @throws GuzzleException
     */
    public function listHeaders(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null, ?int $skipRows = null): array
    {
        return $this->listDocument('prdh', $fieldList, self::DEFAULT_PRODUCTION_ORDER_FIELD_LIST, $filter, $numRows, $sort, $skipRows);
    }

    /**
     * @param  string[]  $fieldList
     *
     * @throws GuzzleException
     */
    public function findHeaderByProductionOrderNumber(string|int $productionOrderNumber, array $fieldList = []): array
    {
        return $this->listHeaders(
            $fieldList,
            $this->buildFilter('prdh_num', '=', (string) $productionOrderNumber),
            1
        );
    }

    /**
     * Returns only the prdh rows from the MKG response.
     *
     * @param  string[]  $fieldList
     * @return array<int, array<string, mixed>>
     *
     * @throws GuzzleException
     */
    public function findHeaderRowsByProductionOrderNumber(string|int $productionOrderNumber, array $fieldList = []): array
    {
        return $this->extractHeaderRows(
            $this->findHeaderByProductionOrderNumber($productionOrderNumber, $fieldList)
        );
    }

    /**
     * Turns an MKG prdh response into a plain row array.
     *
     * The package ships no field metadata for prdh, so the values stay as MKG sent them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractHeaderRows(array $response): array
    {
        return $this->extractRowsFromResultData($response, 'prdh');
    }

    /**
     * Production order rows (prdr).
     *
     * @param  string[]  $fieldList
     *
     * @throws GuzzleException
     */
    public function listRows(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null, ?int $skipRows = null): array
    {
        return $this->listDocument('prdr', $fieldList, self::DEFAULT_PRODUCTION_ORDER_ROW_FIELD_LIST, $filter, $numRows, $sort, $skipRows);
    }

    /**
     * The rows of one production order, in MKG's own order, up to 1000 rows.
     *
     * @param  string[]  $fieldList
     *
     * @throws GuzzleException
     */
    public function findRowsByProductionOrderNumber(string|int $productionOrderNumber, array $fieldList = []): array
    {
        return $this->listRows(
            $fieldList,
            $this->buildFilter('prdh_num', '=', (string) $productionOrderNumber),
            self::MAX_ROWS_PER_CALL,
            'prdr_volgorde'
        );
    }

    /**
     * Returns only the prdr rows from the MKG response.
     *
     * @param  string[]  $fieldList
     * @return array<int, array<string, mixed>>
     *
     * @throws GuzzleException
     */
    public function findLineRowsByProductionOrderNumber(string|int $productionOrderNumber, array $fieldList = []): array
    {
        return $this->extractLineRows(
            $this->findRowsByProductionOrderNumber($productionOrderNumber, $fieldList)
        );
    }

    /**
     * Turns an MKG prdr response into a plain row array.
     *
     * The package ships no field metadata for prdr, so the values stay as MKG sent them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractLineRows(array $response): array
    {
        return $this->extractRowsFromResultData($response, 'prdr');
    }
}
