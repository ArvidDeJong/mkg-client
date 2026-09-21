<?php

use Darvis\MkgClient\Config\ArrayConfigProvider;
use Darvis\MkgClient\Contracts\CookieStoreInterface;
use Darvis\MkgClient\Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

uses(TestCase::class)->in('.');

/**
 * A Guzzle client on a MockHandler that records every request it sends.
 *
 * @param  array<int, Response|Throwable>  $responses
 * @param  array<int, array<string, mixed>>  $history
 */
function mkgRecordingClient(array $responses, array &$history): Client
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    return new Client([
        'handler' => $stack,
        'allow_redirects' => false,
        'http_errors' => true,
    ]);
}

function mkgTestConfig(): ArrayConfigProvider
{
    return new ArrayConfigProvider([
        'mkg.url_auth' => 'https://example.test/restapi/auth',
        'mkg.url_prod' => 'https://example.test/restapi',
        'mkg.customer' => 'DEMO',
        'mkg.username' => 'demo-user',
        'mkg.password' => 'demo-password',
    ]);
}

/**
 * A cookie store that already holds a session, so no login response is needed.
 */
function mkgTestCookieStore(): CookieStoreInterface
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

function mkgEmptyEnvelope(): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], '{"response":{"ResultData":[{}]}}');
}
