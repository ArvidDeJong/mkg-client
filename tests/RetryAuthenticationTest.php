<?php

use Darvis\MkgClient\Exceptions\MkgHttpException;
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

test('it does not authenticate during construction', function (): void {
    $client = new Client([
        'handler' => HandlerStack::create(new MockHandler([])),
        'http_errors' => true,
    ]);

    expect(fn () => new TestableMkgService($client, null))
        ->not->toThrow(RuntimeException::class);
});

/**
 * De les van 2026-09-01: een 403 komt van Tomcat en betekent een verkeerd pad,
 * geen verlopen sessie. Opnieuw inloggen verdubbelt dan het verkeer en faalt
 * alsnog. Alleen 401 hoort een herlogin uit te lokken.
 */
test('it does not re-authenticate on a 403 and explains the wrong base url', function (): void {
    config()->set('mkg.cookie_storage_path', 'mkg/test-cookie.txt');
    config()->set('mkg.host', 'mkg.example.com');
    config()->set('mkg.url_prod', null);

    Storage::put('mkg/test-cookie.txt', 'JSESSIONID=valid-cookie');

    // Eén respons in de mock: een tweede aanroep zou "queue empty" opleveren.
    $mock = new MockHandler([
        new ClientException(
            'Client error: 403',
            new Request('GET', 'https://mkg.example.com/mkg/rest/v3/MKG/Documents/debi'),
            new Response(403, [], '<!doctype html><html><head><title>HTTP Status 403</title></head></html>'),
        ),
    ]);

    $client = new Client([
        'handler' => HandlerStack::create($mock),
        'http_errors' => true,
    ]);

    $service = new TestableMkgService($client, 'JSESSIONID=valid-cookie');

    expect(fn () => $service->callRequestJson('GET', '/debi'))
        ->toThrow(MkgHttpException::class, 'web/v3');

    // De cookie is niet weggegooid: er was niets mis met de sessie.
    expect(Storage::get('mkg/test-cookie.txt'))->toBe('JSESSIONID=valid-cookie');
});

test('it surfaces the json error body that MKG returns', function (): void {
    config()->set('mkg.cookie_storage_path', 'mkg/test-cookie.txt');
    config()->set('mkg.host', 'mkg.example.com');
    config()->set('mkg.url_prod', null);

    Storage::put('mkg/test-cookie.txt', 'JSESSIONID=valid-cookie');

    $mock = new MockHandler([
        new ClientException(
            'Client error: 400',
            new Request('GET', 'https://mkg.example.com/mkg/web/v3/MKG/Documents/debi'),
            new Response(400, [], '{"status_code":400,"status_txt":"Unknown field debi_bogus"}'),
        ),
    ]);

    $service = new TestableMkgService(
        new Client(['handler' => HandlerStack::create($mock), 'http_errors' => true]),
        'JSESSIONID=valid-cookie'
    );

    expect(fn () => $service->callRequestJson('GET', '/debi'))
        ->toThrow(MkgHttpException::class, 'Unknown field debi_bogus');
});
