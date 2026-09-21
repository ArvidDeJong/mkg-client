<?php

namespace Darvis\MkgClient;

use Darvis\MkgClient\Config\ChainConfigProvider;
use Darvis\MkgClient\Config\EnvConfigProvider;
use Darvis\MkgClient\Config\LaravelConfigProvider;
use Darvis\MkgClient\Contracts\ConfigProviderInterface;
use Darvis\MkgClient\Contracts\CookieStoreInterface;
use Darvis\MkgClient\Cookies\FileCookieStore;
use Darvis\MkgClient\Cookies\LaravelCookieStore;
use Darvis\MkgClient\Exceptions\MkgHttpException;
use Darvis\MkgClient\Support\FieldMetaNormalizer;
use Darvis\MkgClient\Support\MkgEndpoints;
use Darvis\MkgClient\Support\RequestLogger;
use Darvis\MkgClient\Support\SessionCookieManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

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

    private ?MkgEndpoints $endpoints = null;

    private ?RequestLogger $requestLogger = null;

    protected ?LoggerInterface $logger = null;

    public function __construct(
        ?Client $client = null,
        ?ConfigProviderInterface $config = null,
        ?CookieStoreInterface $cookieStore = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->config = $config ?? $this->createDefaultConfigProvider();
        $this->logger = $logger;
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
            // Only 401 means "session expired"; MKG answers that with JSON. A 403
            // comes from Tomcat and means the URL is wrong, so re-authenticating
            // would just double the traffic and fail again.
            if ($e->getResponse()->getStatusCode() !== 401) {
                throw MkgHttpException::from($e, $this->endpoints()->rest());
            }

            // Expired session cookie: clear cached cookie, login again, retry once.
            $this->sessionManager()->refresh();
            $headers = $this->sessionManager()->buildHeaders(isset($options['json']));
            $this->syncSessionCookie();

            try {
                $response = $this->client->request($method, $this->buildUrl($path), array_merge($options, [
                    'headers' => $headers,
                ]));
            } catch (ClientException $retry) {
                throw MkgHttpException::from($retry, $this->endpoints()->rest());
            }
        }

        return $this->decodeResponse($method, $path, $response);
    }

    /**
     * Turns a response into the decoded JSON array, or fails loudly.
     *
     * Redirects are off, so a 3xx arrives here as a normal response, and a 200
     * can carry an HTML page (a login form, a proxy error). Both used to come
     * back as `[]`, which a caller cannot tell from "no rows": a sync would
     * conclude that every order is gone. Only a body that really is JSON counts.
     *
     * @throws MkgHttpException
     */
    private function decodeResponse(string $method, string $path, ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        $contents = (string) $response->getBody();

        if ($status >= 300 && $status < 400) {
            throw $this->unusableResponse($method, $path, $response, sprintf(
                'MKG answered %d, a redirect, where a JSON document was expected. The client does not follow redirects: '
                .'check the configured base URL (%s) and whether a proxy or login page sits in front of MKG.',
                $status,
                $this->endpoints()->rest(),
            ));
        }

        // A write may be answered without a body; a read never is.
        if (trim($contents) === '' && ($status === 204 || strtoupper($method) !== 'GET')) {
            return [];
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw $this->unusableResponse($method, $path, $response, sprintf(
                'MKG answered %d with a body that is not valid JSON (Content-Type: %s, %d bytes), so it is not an empty result. '
                .'Check the configured base URL (%s) and whether a proxy or login page sits in front of MKG.',
                $status,
                $response->getHeaderLine('Content-Type') ?: 'none',
                strlen($contents),
                $this->endpoints()->rest(),
            ));
        }

        return $decoded;
    }

    /**
     * The message never holds the body or the Location header: both can carry a session id.
     */
    private function unusableResponse(string $method, string $path, ResponseInterface $response, string $message): MkgHttpException
    {
        $this->requestLogger()->unusableResponse(
            $method,
            (string) parse_url($this->buildUrl($path), PHP_URL_PATH),
            $response->getStatusCode(),
            $message,
        );

        return new MkgHttpException($message, new Request($method, $this->buildUrl($path)), $response);
    }

    /**
     * Builds `field operator value` for a key lookup.
     *
     * An unquoted value is part of the filter expression, so only a plain
     * number may go in unquoted. MKG keys such as `vorh_num` and `debi_num` are
     * character fields (`VK2606096`), so any other value is compared as a
     * quoted, escaped text instead of being refused.
     *
     * @throws InvalidArgumentException
     */
    protected function buildFilter(string $field, string $operator, string|int|float $value): string
    {
        if (is_int($value)) {
            return sprintf('%s %s %d', $field, $operator, $value);
        }

        $text = trim((string) $value);

        if ($text === '') {
            throw new InvalidArgumentException(sprintf('The MKG filter value for "%s" is empty.', $field));
        }

        if (preg_match('/\A-?\d+(\.\d+)?\z/', $text) === 1) {
            return sprintf('%s %s %s', $field, $operator, $text);
        }

        if (is_float($value) || ! in_array(trim($operator), ['=', '<>'], true)) {
            throw new InvalidArgumentException(sprintf(
                'The MKG filter value for "%s" must be a plain number to be compared with "%s".',
                $field,
                $operator,
            ));
        }

        return sprintf('%s %s %s', $field, trim($operator), $this->quoteFilterText($field, $text));
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function buildContainsTextFilter(string $field, string $value): string
    {
        return sprintf('%s contains %s', $field, $this->quoteFilterText($field, $value));
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function buildEqualsTextFilter(string $field, string $value): string
    {
        return sprintf('%s = %s', $field, $this->quoteFilterText($field, $value));
    }

    /**
     * Quotes a text for the MKG filter. The backslash is escaped before the
     * quote: the other way round a value ending in a backslash would escape
     * the closing quote and the rest of the filter would become part of it.
     *
     * @throws InvalidArgumentException
     */
    private function quoteFilterText(string $field, string $value): string
    {
        $text = trim($value);

        if (preg_match('/[\x00-\x1F\x7F]/', $text) === 1) {
            throw new InvalidArgumentException(sprintf(
                'The MKG filter value for "%s" contains a control character (a line break, a tab or a NUL byte).',
                $field,
            ));
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $text).'"';
    }

    /**
     * Encodes one caller supplied part of the URL path.
     *
     * A key is data, not a path: without encoding a `/`, `..`, `?` or `#` in it
     * changes which MKG document the request reaches. `$allowCompositeKey` keeps
     * the `+` that MKG uses between the parts of a composite primary key
     * (`1+VK2606096`), for the methods that take the whole key as one argument.
     *
     * @throws InvalidArgumentException
     */
    protected function encodePathSegment(string|int $segment, bool $allowCompositeKey = false): string
    {
        $segment = (string) $segment;

        if ($segment === '' || $segment === '.' || $segment === '..') {
            throw new InvalidArgumentException(sprintf(
                'An MKG path segment must not be empty, "." or "..", got "%s".',
                $segment,
            ));
        }

        $encoded = rawurlencode($segment);

        return $allowCompositeKey ? str_replace('%2B', '+', $encoded) : $encoded;
    }

    /**
     * @param  string[]  $fieldList
     * @param  string[]  $defaultFieldList
     *
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
    ): array {
        if ($fieldList === []) {
            $fieldList = $defaultFieldList;
        }

        return $this->get('/'.$this->encodePathSegment($document), $this->buildListQuery($fieldList, $filter, $numRows, $sort, $skipRows));
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
    ): array {
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
                $uniqueKey = $uniqueValue ?: md5((string) json_encode($row));

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

        $this->fieldMetaNormalizer = new FieldMetaNormalizer;

        return $this->fieldMetaNormalizer;
    }

    private function buildUrl(string $path): string
    {
        $uri = ltrim($path, '/');

        return $this->endpoints()->rest().'/'.$uri;
    }

    private function createDefaultConfigProvider(): ConfigProviderInterface
    {
        return new ChainConfigProvider([
            new LaravelConfigProvider,
            new EnvConfigProvider,
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

    private function requestLogger(): RequestLogger
    {
        return $this->requestLogger ??= new RequestLogger(
            $this->logger,
            $this->toBool($this->config->get('mkg.log_requests', false), false),
            $this->toFloat($this->config->get('mkg.slow_request_seconds', 10), 10.0),
        );
    }

    private function createDefaultClient(): Client
    {
        $stack = HandlerStack::create();
        $stack->push($this->requestLogger()->middleware());

        return new Client([
            'handler' => $stack,
            'allow_redirects' => false,
            'verify' => $this->toBool($this->config->get('mkg.verify_ssl', true), true),
            'timeout' => $this->toFloat($this->config->get('mkg.timeout', 30), 30.0),
            'connect_timeout' => $this->toFloat($this->config->get('mkg.connect_timeout', 10), 10.0),
            'http_errors' => true,
        ]);
    }

    protected function endpoints(): MkgEndpoints
    {
        return $this->endpoints ??= new MkgEndpoints($this->config);
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
            return new LaravelCookieStore;
        }

        return new FileCookieStore;
    }
}
