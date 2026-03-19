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
        return $this->listDocument('gebr', $fieldList, $this->getDefaultUserSearchFieldList(), $filter, $numRows, $sort);
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
            $this->buildContainsTextFilter('gebr_naam', $name),
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

        return $this->mergeUniqueRows($rowsByCode, $rowsByName, 'RowKey');
    }

    /**
     * @return string[]
     */
    public function getDefaultUserSearchFieldList(): array
    {
        return $this->filterAvailableFieldList(self::DEFAULT_USER_SEARCH_FIELD_LIST, $this->getUserFieldMeta());
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
        return $this->getCachedFieldMeta(self::$userFieldMeta, 'gebr');
    }

    /**
     * MKG responses are commonly returned as response.ResultData[0].gebr.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractUserRows(array $response): array
    {
        return $this->extractNormalizedRows($response, 'gebr', $this->getUserFieldMeta());
    }
}
