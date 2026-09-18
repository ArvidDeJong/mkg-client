<?php

use Darvis\MkgClient\Config\ArrayConfigProvider;
use Darvis\MkgClient\Contracts\CookieStoreInterface;
use Darvis\MkgClient\Services\ContactpersonService;
use Darvis\MkgClient\Services\UserService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

function makeArrayConfigProvider(): ArrayConfigProvider
{
    return new ArrayConfigProvider([
        'mkg.url_auth' => 'https://example.test/restapi/auth',
        'mkg.url_prod' => 'https://example.test/restapi',
        'mkg.customer' => 'DEMO',
        'mkg.username' => 'demo-user',
        'mkg.password' => 'demo-password',
    ]);
}

function makeAuthenticatedCookieStore(): CookieStoreInterface
{
    return new class implements CookieStoreInterface
    {
        public function read(string $path): ?string
        {
            return 'JSESSIONID=existing';
        }

        public function write(string $path, string $value): void {}

        public function delete(string $path): void {}
    };
}

function makeMockClient(): Client
{
    return new Client([
        'handler' => HandlerStack::create(new MockHandler([])),
        'http_errors' => true,
    ]);
}

test('user service normalizes extracted rows using csv metadata', function (): void {
    $service = new UserService(
        makeMockClient(),
        makeArrayConfigProvider(),
        makeAuthenticatedCookieStore(),
    );

    $rows = $service->extractUserRows([
        'response' => [
            'ResultData' => [[
                'gebr' => [[
                    'gebr_actief' => 'WAAR',
                    'gebr_licentie_meld_dagen' => '12',
                    'gebr_code' => 123,
                ]],
            ]],
        ],
    ]);

    expect($rows)->toHaveCount(1);
    expect($rows[0]['gebr_actief'])->toBeTrue();
    expect($rows[0]['gebr_licentie_meld_dagen'])->toBe(12);
    expect($rows[0]['gebr_code'])->toBe('123');
});

test('contactperson service normalizes extracted rows using csv metadata', function (): void {
    $service = new ContactpersonService(
        makeMockClient(),
        makeArrayConfigProvider(),
        makeAuthenticatedCookieStore(),
    );

    $rows = $service->extractContactpersonRows([
        'response' => [
            'ResultData' => [[
                'cprs' => [[
                    'cprs_actief' => 'nee',
                    'cprs_num' => '5',
                    'cprs_email' => 123,
                ]],
            ]],
        ],
    ]);

    expect($rows)->toHaveCount(1);
    expect($rows[0]['cprs_actief'])->toBeFalse();
    expect($rows[0]['cprs_num'])->toBe(5);
    expect($rows[0]['cprs_email'])->toBe('123');
});
