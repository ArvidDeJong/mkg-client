<?php

use Darvis\MkgClient\Exceptions\MkgHttpException;
use Darvis\MkgClient\Services\DebtorsService;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;

function debtorsServiceWith(array $responses, ?LoggerInterface $logger = null): DebtorsService
{
    $history = [];

    return new DebtorsService(mkgRecordingClient($responses, $history), mkgTestConfig(), mkgTestCookieStore(), $logger);
}

it('throws on a redirect instead of returning no rows', function (): void {
    $service = debtorsServiceWith([new Response(302, ['Location' => 'https://example.test/mkg/login.html'])]);

    try {
        $service->list(['debi_num']);
    } catch (MkgHttpException $e) {
        expect($e->getResponse()->getStatusCode())->toBe(302);
        expect($e->getCode())->toBe(302);
        expect($e->getMessage())->toContain('302')->toContain('redirect');
        // The target can carry a session id or a token.
        expect($e->getMessage())->not->toContain('login.html');

        return;
    }

    $this->fail('A redirect was returned as an empty result.');
});

it('throws on a 200 that is not json instead of returning no rows', function (string $body): void {
    $service = debtorsServiceWith([new Response(200, ['Content-Type' => 'text/html'], $body)]);

    expect(fn () => $service->list(['debi_num']))->toThrow(MkgHttpException::class, 'not valid JSON');
})->with([
    'login page' => ['<!doctype html><html><body><form action="j_spring_security_check"></form></body></html>'],
    'plain text' => ['Service temporarily unavailable'],
    'empty body on a read' => [''],
    'json scalar' => ['"ok"'],
]);

it('keeps the body of the unusable response out of the message', function (): void {
    $service = debtorsServiceWith([new Response(200, [], '<html>secret-token-123</html>')]);

    try {
        $service->list(['debi_num']);
    } catch (MkgHttpException $e) {
        expect($e->getMessage())->not->toContain('secret-token-123');

        return;
    }

    $this->fail('Expected an MkgHttpException.');
});

it('logs the unusable response as a warning', function (): void {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'MKG response was not usable.'
                && $context['method'] === 'GET'
                && $context['status'] === 302
                && str_contains($context['path'], '/debi')
                && is_string($context['reason']);
        });

    $service = debtorsServiceWith([new Response(302, ['Location' => '/login'])], $logger);

    expect(fn () => $service->list(['debi_num']))->toThrow(MkgHttpException::class);
});

it('still returns an empty array for a genuine empty result', function (string $body): void {
    $service = debtorsServiceWith([new Response(200, ['Content-Type' => 'application/json'], $body)]);

    expect($service->extractDebtorRows($service->list(['debi_num'])))->toBe([]);
})->with([
    'empty list' => ['[]'],
    'empty object' => ['{}'],
    'empty MKG envelope' => ['{"response":{"ResultData":[{}]}}'],
]);

it('still logs in again and retries once on a 401', function (): void {
    $history = [];
    $client = mkgRecordingClient([
        new ClientException('Unauthorized', new Request('GET', 'https://example.test/restapi/debi'), new Response(401)),
        new Response(200, ['Set-Cookie' => 'JSESSIONID=fresh; Path=/; HttpOnly'], ''),
        new Response(200, [], '{"response":{"ResultData":[{"debi":[{"debi_num":"10001"}]}]}}'),
    ], $history);

    $service = new DebtorsService($client, mkgTestConfig(), mkgTestCookieStore());

    expect($service->findDebtorRowsByDebtorNumber(10001, ['debi_num']))->toBe([['debi_num' => '10001']]);
    expect($history)->toHaveCount(3);
    expect($history[2]['request']->getHeaderLine('Cookie'))->toBe('JSESSIONID=fresh');
});

it('throws when the retry after a 401 ends in a redirect', function (): void {
    $service = debtorsServiceWith([
        new ClientException('Unauthorized', new Request('GET', 'https://example.test/restapi/debi'), new Response(401)),
        new Response(200, ['Set-Cookie' => 'JSESSIONID=fresh; Path=/; HttpOnly'], ''),
        new Response(302, ['Location' => '/login']),
    ]);

    expect(fn () => $service->list(['debi_num']))->toThrow(MkgHttpException::class, '302');
});
