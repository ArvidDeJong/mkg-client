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
        return $this->listDocument('arti', $fieldList, $this->getDefaultArticleSearchFieldList(), $filter, $numRows, $sort);
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
            $this->buildContainsTextFilter('arti_oms_1', $name),
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

        return $this->mergeUniqueRows($rowsByCode, $rowsByName, 'arti_code');
    }

    /**
     * @return string[]
     */
    public function getDefaultArticleSearchFieldList(): array
    {
        return $this->filterAvailableFieldList(self::DEFAULT_ARTICLE_SEARCH_FIELD_LIST, $this->getArticleFieldMeta());
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
        return $this->getCachedFieldMeta(self::$articleFieldMeta, 'arti');
    }

    /**
     * MKG responses are commonly returned as response.ResultData[0].arti.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractArticleRows(array $response): array
    {
        return $this->extractNormalizedRows($response, 'arti', $this->getArticleFieldMeta());
    }
}
