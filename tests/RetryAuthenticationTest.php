<?php

use Darvis\MkgClient\Tests\Fixtures\TestableMkgService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;

test('it invalidates cookie, re-authenticates and retries request on 401', function (): void {
    config()->set('mkg.cookie_storage_path', 'mkg/test-cookie.txt');

    Storage::put('mkg/test-cookie.txt', 'JSESSIONID=stale-cookie');

    $mock = new MockHandler([
        new ClientException('Unauthorized', new Request('GET', 'https://example.test/restapi/debi'), new Response(401)),
        new Response(200, ['Set-Cookie' => 'JSESSIONID=fresh-cookie; Path=/; HttpOnly'], ''),
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'response' => [
                'ResultData' => [
                    ['debi' => [['debi_num' => '10001']]],
                ],
            ],
        ])),
    ]);

    $client = new Client([
        'handler' => HandlerStack::create($mock),
        'http_errors' => true,
    ]);

    $service = new TestableMkgService($client, 'JSESSIONID=stale-cookie');

    $result = $service->callRequestJson('GET', '/debi');

    expect($result['response']['ResultData'][0]['debi'][0]['debi_num'] ?? null)->toBe('10001');
    expect(Storage::get('mkg/test-cookie.txt'))->toBe('JSESSIONID=fresh-cookie');
});
