<?php

namespace Darvis\MkgClient;

use Darvis\MkgClient\Config\ChainConfigProvider;
use Darvis\MkgClient\Config\EnvConfigProvider;
use Darvis\MkgClient\Config\LaravelConfigProvider;
use Darvis\MkgClient\Contracts\ConfigProviderInterface;
use Darvis\MkgClient\Contracts\CookieStoreInterface;
use Darvis\MkgClient\Cookies\FileCookieStore;
use Darvis\MkgClient\Cookies\LaravelCookieStore;
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

    public function __construct(
        ?Client $client = null,
        ?ConfigProviderInterface $config = null,
        ?CookieStoreInterface $cookieStore = null,
    )
    {
        $this->config = $config ?? new ChainConfigProvider([
            new LaravelConfigProvider(),
            new EnvConfigProvider(),
        ]);
        $this->cookieStore = $cookieStore ?? $this->resolveDefaultCookieStore();

        $this->cookieStoragePath = (string) $this->config->get('mkg.cookie_storage_path', 'mkg/cookie.txt');

        $this->client = $client ?? new Client([
            'allow_redirects' => false,
            'verify' => $this->toBool($this->config->get('mkg.verify_ssl', true), true),
            'timeout' => $this->toFloat($this->config->get('mkg.timeout', 30), 30.0),
            'connect_timeout' => $this->toFloat($this->config->get('mkg.connect_timeout', 10), 10.0),
            'http_errors' => true,
        ]);

        $this->sessionCookie = $this->readSessionCookie();
        if (! $this->sessionCookie) {
            $this->login();
        }
    }

    /**
     * @throws GuzzleException
     */
    protected function login(): void
    {
        $this->assertRequiredConfig([
            'mkg.url_auth',
            'mkg.customer',
            'mkg.username',
            'mkg.password',
        ]);

        $response = $this->client->post((string) $this->config->get('mkg.url_auth'), [
            'headers' => [
                'X-CustomerID' => $this->config->get('mkg.customer'),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'j_username' => $this->config->get('mkg.username'),
                'j_password' => $this->config->get('mkg.password'),
            ],
        ]);

        $cookie = $response->getHeaderLine('Set-Cookie');
        $this->sessionCookie = explode(';', $cookie)[0] ?? null;

        if ($this->sessionCookie) {
            $this->cookieStore->write($this->cookieStoragePath, $this->sessionCookie);
        }
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
        if (! $this->sessionCookie) {
            $this->login();
        }

        $headers = [
            'X-CustomerID' => $this->config->get('mkg.customer'),
            'Cookie' => $this->sessionCookie,
            'Accept' => 'application/json',
        ];

        if (isset($options['json'])) {
            $headers['Content-Type'] = 'application/json';
        }

        try {
            $response = $this->client->request($method, $this->buildUrl($path), array_merge($options, [
                'headers' => $headers,
            ]));
        } catch (ClientException $e) {
            if ($e->getCode() !== 401) {
                throw $e;
            }

            // Expired session cookie: clear cached cookie, login again, retry once.
            $this->invalidateSessionCookie();
            $this->login();

            $headers['Cookie'] = $this->sessionCookie;
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
        $fields = [];

        foreach ($meta as $fieldName => $fieldMeta) {
            if ($databaseOnly && ! $fieldMeta['isDatabaseField']) {
                continue;
            }

            $fields[] = $fieldName;
        }

        return $fields;
    }

    /**
     * @return array<string, array{label: string, type: string, isDatabaseField: bool}>
     */
    protected function loadFieldMetaFromCsv(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $meta = [];

        try {
            // Skip header row.
            fgetcsv($handle, 0, ';');

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $fieldName = isset($row[0]) ? trim((string) $row[0]) : '';

                if ($fieldName === '') {
                    continue;
                }

                $label = isset($row[1]) ? trim((string) $row[1]) : '';
                $type = isset($row[3]) ? trim((string) $row[3]) : '';
                $isDatabaseField = isset($row[5])
                    ? strtoupper(trim((string) $row[5])) === 'WAAR'
                    : false;

                $meta[$fieldName] = [
                    'label' => $label,
                    'type' => strtolower($type),
                    'isDatabaseField' => $isDatabaseField,
                ];
            }
        } finally {
            fclose($handle);
        }

        return $meta;
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
     * Applies type-based normalization for known fields from metadata.
     * Unknown fields are returned unchanged.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array{label: string, type: string, isDatabaseField: bool}>  $meta
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeRows(array $rows, array $meta): array
    {
        if ($meta === []) {
            return $rows;
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $fieldName => $value) {
                if (! isset($meta[$fieldName])) {
                    continue;
                }

                $rows[$rowIndex][$fieldName] = $this->normalizeValueByType($value, $meta[$fieldName]['type']);
            }
        }

        return $rows;
    }

    protected function normalizeValueByType(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = strtolower(trim($type));

        if (in_array($type, ['integer'], true)) {
            return is_numeric($value) ? (int) $value : $value;
        }

        if (in_array($type, ['decimal', 'percentage', 'bedrag'], true)) {
            return $this->toFloatOrOriginal($value);
        }

        if (in_array($type, ['logical'], true)) {
            return $this->toBoolOrOriginal($value);
        }

        if (in_array($type, ['datum', 'date'], true)) {
            return $this->normalizeDateOrOriginal($value);
        }

        if (in_array($type, ['character', 'omschrijving', 'memo', 'e-mail', 'email', 'naw'], true)) {
            return is_scalar($value) ? (string) $value : $value;
        }

        return $value;
    }

    protected function toFloatOrOriginal(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $trimmed = trim($value);
        $normalized = str_replace('.', '', $trimmed);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : $value;
    }

    protected function toBoolOrOriginal(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            if ($value === 1 || $value === 1.0) {
                return true;
            }

            if ($value === 0 || $value === 0.0) {
                return false;
            }

            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['1', 'true', 'waar', 'yes', 'ja'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'onwaar', 'no', 'nee'], true)) {
            return false;
        }

        return $value;
    }

    protected function normalizeDateOrOriginal(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return $value;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
            return $trimmed;
        }

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $trimmed, $matches) === 1) {
            return $matches[3].'-'.$matches[2].'-'.$matches[1];
        }

        return $value;
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

    private function readSessionCookie(): ?string
    {
        $cookie = $this->cookieStore->read($this->cookieStoragePath);

        if ($cookie === null) {
            return null;
        }

        if ($cookie === '' || ! str_starts_with($cookie, 'JSESSIONID=')) {
            return null;
        }

        return $cookie;
    }

    private function invalidateSessionCookie(): void
    {
        $this->sessionCookie = null;

        $this->cookieStore->delete($this->cookieStoragePath);
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

    private function resolveDefaultCookieStore(): CookieStoreInterface
    {
        if (class_exists('Illuminate\\Support\\Facades\\Storage')) {
            return new LaravelCookieStore();
        }

        return new FileCookieStore();
    }
}
