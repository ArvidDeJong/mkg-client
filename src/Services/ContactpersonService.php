<?php

namespace Darvis\MkgClient\Services;

use Darvis\MkgClient\BaseMkgService;
use GuzzleHttp\Exception\GuzzleException;

class ContactpersonService extends BaseMkgService
{
    /**
     * Default fields for contact person search/sync requests.
     *
     * @var string[]
     */
    public const DEFAULT_CONTACTPERSON_SEARCH_FIELD_LIST = [
        'sys_dat_aanm',
        'sys_tijd_aanm',
        'sys_dat_wijzig',
        'sys_tijd_wijzig',
        'cprs_num',
        'rela_num',
        'cprs_naam',
        'cprs_email',
        'cprs_actief',
    ];

    /** @var array<string, array{label: string, type: string, isDatabaseField: bool}>|null */
    private static ?array $contactpersonFieldMeta = null;

    /**
     * @throws GuzzleException
     */
    public function list(array $fieldList = [], ?string $filter = null, ?int $numRows = null, ?string $sort = null): array
    {
        return $this->listDocument('cprs', $fieldList, $this->getDefaultContactpersonSearchFieldList(), $filter, $numRows, $sort);
    }

    /**
     * @throws GuzzleException
     */
    public function findByContactpersonNumber(string|int $contactpersonNumber, array $fieldList = []): array
    {
        return $this->list(
            $fieldList,
            $this->buildFilter('cprs_num', '=', (string) $contactpersonNumber),
            1,
            'sys_tijd_wijzig'
        );
    }

    /**
     * Returns only parsed cprs rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findContactpersonRowsByContactpersonNumber(string|int $contactpersonNumber, array $fieldList = []): array
    {
        return $this->extractContactpersonRows(
            $this->findByContactpersonNumber($contactpersonNumber, $fieldList)
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
            'cprs_naam'
        );
    }

    /**
     * Returns only parsed cprs rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findContactpersonRowsByRelationNumber(string|int $relationNumber, array $fieldList = [], int $numRows = 25): array
    {
        return $this->extractContactpersonRows(
            $this->findByRelationNumber($relationNumber, $fieldList, $numRows)
        );
    }

    /**
     * @throws GuzzleException
     */
    public function findByContactpersonName(string $contactpersonName, array $fieldList = [], int $numRows = 25): array
    {
        $name = trim($contactpersonName);

        if ($name == '') {
            return [];
        }

        return $this->list(
            $fieldList,
            $this->buildContainsTextFilter('cprs_naam', $name),
            $numRows,
            'cprs_naam'
        );
    }

    /**
     * Returns only parsed cprs rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findContactpersonRowsByContactpersonName(string $contactpersonName, array $fieldList = [], int $numRows = 25): array
    {
        return $this->extractContactpersonRows(
            $this->findByContactpersonName($contactpersonName, $fieldList, $numRows)
        );
    }

    /**
     * @throws GuzzleException
     */
    public function findByContactpersonEmail(string $contactpersonEmail, array $fieldList = [], int $numRows = 25): array
    {
        $email = trim($contactpersonEmail);

        if ($email == '') {
            return [];
        }

        return $this->list(
            $fieldList,
            $this->buildContainsTextFilter('cprs_email', $email),
            $numRows,
            'cprs_email'
        );
    }

    /**
     * Returns only parsed cprs rows from the MKG response.
     *
     * @throws GuzzleException
     */
    public function findContactpersonRowsByContactpersonEmail(string $contactpersonEmail, array $fieldList = [], int $numRows = 25): array
    {
        return $this->extractContactpersonRows(
            $this->findByContactpersonEmail($contactpersonEmail, $fieldList, $numRows)
        );
    }

    /**
     * Searches by contact person number when input is numeric, otherwise by name.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws GuzzleException
     */
    public function findContactpersonRowsByNumberNameOrEmail(string $search, array $fieldList = [], int $numRows = 25): array
    {
        $query = trim($search);

        if ($query == '') {
            return [];
        }

        if (ctype_digit($query)) {
            $rowsByContactpersonNumber = $this->findContactpersonRowsByContactpersonNumber($query, $fieldList);
            $rowsByRelationNumber = $this->findContactpersonRowsByRelationNumber($query, $fieldList, $numRows);

            return $this->mergeUniqueRows($rowsByContactpersonNumber, $rowsByRelationNumber, 'cprs_num');
        }

        if (str_contains($query, '@')) {
            return $this->findContactpersonRowsByContactpersonEmail($query, $fieldList, $numRows);
        }

        return $this->findContactpersonRowsByContactpersonName($query, $fieldList, $numRows);
    }

    /**
     * Backward compatible alias.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws GuzzleException
     */
    public function findContactpersonRowsByNumberOrName(string $search, array $fieldList = [], int $numRows = 25): array
    {
        return $this->findContactpersonRowsByNumberNameOrEmail($search, $fieldList, $numRows);
    }

    /**
     * @return string[]
     */
    public function getDefaultContactpersonSearchFieldList(): array
    {
        return $this->filterAvailableFieldList(self::DEFAULT_CONTACTPERSON_SEARCH_FIELD_LIST, $this->getContactpersonFieldMeta());
    }

    /**
     * @return string[]
     */
    public function getDefaultContactpersonFieldList(bool $databaseOnly = false): array
    {
        $meta = $this->getContactpersonFieldMeta();

        if ($meta === []) {
            return self::DEFAULT_CONTACTPERSON_SEARCH_FIELD_LIST;
        }

        return $this->extractFieldNames($meta, $databaseOnly);
    }

    /**
     * @return array<string, array{label: string, type: string, isDatabaseField: bool}>
     */
    public function getContactpersonFieldMeta(): array
    {
        return $this->getCachedFieldMeta(self::$contactpersonFieldMeta, 'cprs');
    }

    /**
     * MKG responses are commonly returned as response.ResultData[0].cprs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractContactpersonRows(array $response): array
    {
        return $this->extractNormalizedRows($response, 'cprs', $this->getContactpersonFieldMeta());
    }
}
