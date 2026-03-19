<?php

namespace Darvis\MkgClient\Tests\Fixtures;

use Darvis\MkgClient\BaseMkgService;
use Darvis\MkgClient\Config\ChainConfigProvider;
use Darvis\MkgClient\Config\LaravelConfigProvider;
use Darvis\MkgClient\Cookies\FileCookieStore;
use Darvis\MkgClient\Cookies\LaravelCookieStore;
use GuzzleHttp\Client;

class TestableMkgService extends BaseMkgService
{
    public function __construct(Client $client, ?string $sessionCookie = 'JSESSIONID=existing')
    {
        $this->client = $client;
        $this->config = new ChainConfigProvider([
            new LaravelConfigProvider(),
        ]);
        $this->cookieStore = class_exists('Illuminate\\Support\\Facades\\Storage')
            ? new LaravelCookieStore()
            : new FileCookieStore();
        $this->sessionCookie = $sessionCookie;
        $this->cookieStoragePath = (string) $this->config->get('mkg.cookie_storage_path', 'mkg/cookie.txt');
    }

    public function callRequestJson(string $method, string $path, array $options = []): array
    {
        return $this->requestJson($method, $path, $options);
    }

    public function resolvePackageCsvPath(string $variant): string
    {
        return $this->packageCsvPath($variant);
    }

    public function normalizeTypedValue(mixed $value, string $type): mixed
    {
        return $this->normalizeValueByType($value, $type);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array{label: string, type: string, isDatabaseField: bool}>  $meta
     * @return array<int, array<string, mixed>>
     */
    public function normalizeTypedRows(array $rows, array $meta): array
    {
        return $this->normalizeRows($rows, $meta);
    }
}
