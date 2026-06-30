<?php

namespace Darvis\MkgClient;

use Darvis\MkgClient\Config\ChainConfigProvider;
use Darvis\MkgClient\Config\EnvConfigProvider;
use Darvis\MkgClient\Config\LaravelConfigProvider;
use Darvis\MkgClient\Contracts\ConfigProviderInterface;
use Darvis\MkgClient\Contracts\CookieStoreInterface;
use Darvis\MkgClient\Cookies\FileCookieStore;
use Darvis\MkgClient\Cookies\LaravelCookieStore;
use Darvis\MkgClient\Support\FieldMetaNormalizer;
use Darvis\MkgClient\Support\SessionCookieManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

abstract class BaseMkgService
{
    /**
     * Human-readable titles per MKG variant/document.
     *
     * @var array<string, string>
     */
    protected const MKG_VARIANT_TITLES = [
        'vorh' => 'verkooporders',
        'vorr' => 'verkoopregels',
        'vopa' => 'verkooporder regel parameters',
        'arti' => 'artikelen',
        'debi' => 'debiteuren',
        'cprs' => 'contactpersonen',
        'gebr' => 'gebruikers',
        'adrs' => 'adressen',
        'rela' => 'relaties',
        'cred' => 'crediteuren',
    ];

    protected Client $client;

    protected ?string $sessionCookie = null;

    protected string $cookieStoragePath = 'mkg/cookie.txt';

    protected ConfigProviderInterface $config;

    protected CookieStoreInterface $cookieStore;

    private ?FieldMetaNormalizer $fieldMetaNormalizer = null;

    private ?SessionCookieManager $sessionManager = null;

    public function __construct(
        ?Client $client = null,
        ?ConfigProviderInterface $config = null,
        ?CookieStoreInterface $cookieStore = null,
    )
    {
        $this->config = $config ?? $this->createDefaultConfigProvider();
        $this->cookieStore = $cookieStore ?? $this->resolveDefaultCookieStore();
        $this->cookieStoragePath = $this->resolveCookieStoragePath();
        $this->client = $client ?? $this->createDefaultClient();

        $this->validateAuthenticationConfiguration();
    }

    /**
     * @throws GuzzleException
     */
    protected function login(): void
    {
        $this->sessionManager()->login();
        $this->syncSessionCookie();
    }

    /**
     * @throws GuzzleException
     */
    protected function get(string $path, array $query = []): array
    {
        return $this->requestJson('GET', $path, [
            'query' => $query,
        ]);
    }

    /**
     * @throws GuzzleException
     */
    protected function post(string $path, array $payload = []): array
    {
        return $this->requestJson('POST', $path, [
            'json' => $payload,
        ]);
    }

    /**
     * @throws GuzzleException
     */
    protected function put(string $path, array $payload = []): array
    {
        return $this->requestJson('PUT', $path, [
            'json' => $payload,
        ]);
    }

    /**
     * @throws GuzzleException
     */
    protected function requestJson(string $method, string $path, array $options = []): array
    {
        $headers = $this->sessionManager()->buildHeaders(isset($options['json']));
        $this->syncSessionCookie();

        try {
            $response = $this->client->request($method, $this->buildUrl($path), array_merge($options, [
                'headers' => $headers,
            ]));
        } catch (ClientException $e) {
            if ($e->getCode() !== 401) {
                throw $e;
            }

            // Expired session cookie: clear cached cookie, login again, retry once.
            $this->sessionManager()->refresh();
            $headers = $this->sessionManager()->buildHeaders(isset($options['json']));
            $this->syncSessionCookie();

            $response = $this->client->request($method, $this->buildUrl($path), array_merge($options, [
                'headers' => $headers,
            ]));
        }

        $contents = (string) $response->getBody();

        return json_decode($contents, true) ?? [];
    }

    protected function buildFilter(string $field, string $operator, string|int|float $value): string
    {
        // MKG existing integrations typically send unquoted string values in Filter.
        return sprintf('%s %s %s', $field, $operator, $value);
    }

    protected function buildContainsTextFilter(string $field, string $value): string
    {
        $escaped = str_replace('"', '\\"', trim($value));

        return sprintf('%s contains "%s"', $field, $escaped);
    }

    protected function buildEqualsTextFilter(string $field, string $value): string
    {
        $escaped = str_replace('"', '\\"', trim($value));

        return sprintf('%s = "%s"', $field, $escaped);
    }

    /**
     * @param  string[]  $fieldList
     * @param  string[]  $defaultFieldList
     * @throws GuzzleException
     */
    protected function listDocument(
        string $document,
        array $fieldList = [],
        array $defaultFieldList = [],
        ?string $filter = null,
        ?int $numRows = null,
        ?string $sort = null,
        ?int $skipRows = null,
    ): array
    {
        if ($fieldList === []) {
            $fieldList = $defaultFieldList;
        }

        return $this->get('/'.$document, $this->buildListQuery($fieldList, $filter, $numRows, $sort, $skipRows));
    }

    /**
     * @param  string[]  $fieldList
     * @return array<string, mixed>
     */
    protected function buildListQuery(
        array $fieldList = [],
        ?string $filter = null,
        ?int $numRows = null,
        ?string $sort = null,
        ?int $skipRows = null,
    ): array
    {
        $query = [];

        if ($fieldList !== []) {
            $query['FieldList'] = implode(',', $fieldList);
        }

        if ($filter) {
            $query['Filter'] = $filter;
        }

        if ($numRows) {
            $query['NumRows'] = $numRows;
        }

        if ($sort) {
            $query['Sort'] = $sort;
        }

        // SkipRows=0 is a valid first-page offset, so check for null explicitly.
        if ($skipRows !== null) {
            $query['SkipRows'] = $skipRows;
        }

        return $query;
    }

    /**
     * Returns the absolute path to a bundled CSV metadata file for the given MKG variant.
     * Apps can override via storage_path('mkg/{variant}.csv') if they need customization.
     */
    protected function packageCsvPath(string $variant): string
    {
        if (function_exists('storage_path')) {
            $appPath = storage_path('mkg/'.$variant.'.csv');

            if (is_file($appPath) && is_readable($appPath)) {
                return $appPath;
            }
        }

        return __DIR__.'/../resources/csv/'.$variant.'.csv';
    }

    /**
     * @param  array<string, array{label: string, type: string, isDatabaseField: bool}>  $meta
     * @return string[]
     */
    protected function extractFieldNames(array $meta, bool $databaseOnly): array
    {
        return $this->fieldMetaNormalizer()->extractFieldNames($meta, $databaseOnly);
    }

    /**
     * @return array<string, array{label: string, type: string, isDatabaseField: bool}>
     */
    protected function loadFieldMetaFromCsv(string $path): array
    {
        return $this->fieldMetaNormalizer()->loadFieldMetaFromCsv($path);
    }

    /**
     * MKG responses are commonly returned as response.ResultData[0].{document}.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extractRowsFromResultData(array $response, string $document): array
    {
        $rows = $response['response']['ResultData'][0][$document] ?? [];

        if (! is_array($rows) || $rows === []) {
            return [];
        }

        // Single-row responses can be returned as an associative array.
        if (array_keys($rows) !== range(0, count($rows) - 1)) {
            return [$rows];
        }

        return $rows;
    }

    /**
     * @param  array<string, array{label: string, type: string, isDatabaseField: bool}>  $meta
     * @return array<int, array<string, mixed>>
     */
    protected function extractNormalizedRows(array $response, string $document, array $meta): array
    {
        return $this->normalizeRows($this->extractRowsFromResultData($response, $document), $meta);
    }

    /**
     * Applies type-based normalization for known fields from metadata.
     * Unknown fields are returned unchanged.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array{label: string, type: string, isDatabaseField: bool}>  $meta
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeRows(array $rows, array $meta): array
    {
        return $this->fieldMetaNormalizer()->normalizeRows($rows, $meta);
    }

    /**
     * @param  string[]  $defaultFieldList
     * @param  array<string, array{label: string, type: string, isDatabaseField: bool}>  $meta
     * @return string[]
     */
    protected function filterAvailableFieldList(array $defaultFieldList, array $meta): array
    {
        return $this->fieldMetaNormalizer()->filterAvailableFieldList($defaultFieldList, $meta);
    }

    /**
     * @param  array<string, array{label: string, type: string, isDatabaseField: bool}>|null  $cache
     * @return array<string, array{label: string, type: string, isDatabaseField: bool}>
     */
    protected function getCachedFieldMeta(?array &$cache, string $variant): array
    {
        if ($cache !== null) {
            return $cache;
        }

        $cache = $this->loadFieldMetaFromCsv($this->packageCsvPath($variant));

        return $cache;
    }

    /**
     * @param  array<int, array<string, mixed>>  $first
     * @param  array<int, array<string, mixed>>  $second
     * @return array<int, array<string, mixed>>
     */
    protected function mergeUniqueRows(array $first, array $second, ?string $uniqueField = null): array
    {
        $merged = [];
        $seen = [];

        foreach ([$first, $second] as $collection) {
            foreach ($collection as $row) {
                $uniqueValue = $uniqueField !== null && is_scalar($row[$uniqueField] ?? null)
                    ? (string) $row[$uniqueField]
                    : null;
                $uniqueKey = $uniqueValue ?: md5(json_encode($row));

                if (isset($seen[$uniqueKey])) {
                    continue;
                }

                $seen[$uniqueKey] = true;
                $merged[] = $row;
            }
        }

        return $merged;
    }

    protected function normalizeValueByType(mixed $value, string $type): mixed
    {
        return $this->fieldMetaNormalizer()->normalizeValueByType($value, $type);
    }

    /**
     * Returns title for a MKG variant code (e.g. vorh, vorr, vopa, debi).
     */
    public function getMkgVariantTitle(string $variant): ?string
    {
        $key = strtolower(trim($variant));

        return static::MKG_VARIANT_TITLES[$key] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function getMkgVariantTitles(): array
    {
        return static::MKG_VARIANT_TITLES;
    }

    protected function sessionManager(): SessionCookieManager
    {
        if ($this->sessionManager !== null) {
            return $this->sessionManager;
        }

        $this->sessionManager = new SessionCookieManager(
            $this->client,
            $this->config,
            $this->cookieStore,
            $this->cookieStoragePath,
            $this->sessionCookie,
        );

        return $this->sessionManager;
    }

    protected function fieldMetaNormalizer(): FieldMetaNormalizer
    {
        if ($this->fieldMetaNormalizer !== null) {
            return $this->fieldMetaNormalizer;
        }

        $this->fieldMetaNormalizer = new FieldMetaNormalizer();

        return $this->fieldMetaNormalizer;
    }

    private function buildUrl(string $path): string
    {
        $this->assertRequiredConfig(['mkg.url_prod']);

        $baseUrl = rtrim((string) $this->config->get('mkg.url_prod'), '/');
        $uri = ltrim($path, '/');

        return $baseUrl.'/'.$uri;
    }

    /**
     * @param  string[]  $keys
     */
    private function assertRequiredConfig(array $keys): void
    {
        $missing = [];

        foreach ($keys as $key) {
            $value = $this->config->get($key);

            if (! is_scalar($value)) {
                $missing[] = $key;

                continue;
            }

            if (trim((string) $value) === '') {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('Missing MKG configuration values: '.implode(', ', $missing));
        }
    }

    private function createDefaultConfigProvider(): ConfigProviderInterface
    {
        return new ChainConfigProvider([
            new LaravelConfigProvider(),
            new EnvConfigProvider(),
        ]);
    }

    private function resolveCookieStoragePath(): string
    {
        return (string) $this->config->get('mkg.cookie_storage_path', 'mkg/cookie.txt');
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (! is_string($value)) {
            return $default;
        }

        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }

    private function toFloat(mixed $value, float $default): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return $default;
        }

        return is_numeric($value) ? (float) $value : $default;
    }

    private function createDefaultClient(): Client
    {
        return new Client([
            'allow_redirects' => false,
            'verify' => $this->toBool($this->config->get('mkg.verify_ssl', true), true),
            'timeout' => $this->toFloat($this->config->get('mkg.timeout', 30), 30.0),
            'connect_timeout' => $this->toFloat($this->config->get('mkg.connect_timeout', 10), 10.0),
            'http_errors' => true,
        ]);
    }

    private function syncSessionCookie(): void
    {
        $this->sessionCookie = $this->sessionManager()->getSessionCookie();
    }

    private function validateAuthenticationConfiguration(): void
    {
        $this->sessionManager()->validateConfiguration();
    }

    private function resolveDefaultCookieStore(): CookieStoreInterface
    {
        if (class_exists('Illuminate\\Support\\Facades\\Storage')) {
            return new LaravelCookieStore();
        }

        return new FileCookieStore();
    }
}
