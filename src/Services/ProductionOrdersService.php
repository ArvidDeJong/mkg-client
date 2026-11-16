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
}
