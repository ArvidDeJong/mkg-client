<?php

use Darvis\MkgClient\Support\RequestLogger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;

/**
 * MKG-verkeer loopt over kale Guzzle en is daardoor onzichtbaar voor profilers
 * die op Laravel's HTTP-client haken. Zonder deze middleware is een sync die
 * vastloopt niet te plaatsen.
 */
function clientWithLogger(LoggerInterface $logger, bool $logRequests, float $slowSeconds, Response $response): Client
{
    $stack = HandlerStack::create(new MockHandler([$response]));
    $stack->push((new RequestLogger($logger, $logRequests, $slowSeconds))->middleware());

    return new Client(['handler' => $stack, 'http_errors' => true]);
}

it('logs every call at debug level when logging is on', function () {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('debug')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'MKG request completed.'
                && $context['method'] === 'GET'
                && $context['status'] === 200
                && str_contains($context['path'], '/debi')
                && array_key_exists('seconds', $context);
        });

    clientWithLogger($logger, true, 10.0, new Response(200, [], '{}'))
        ->get('https://mkg.example.com/mkg/web/v3/MKG/Documents/debi');
});

it('stays quiet when logging is off', function () {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldNotReceive('debug');
    $logger->shouldNotReceive('warning');

    clientWithLogger($logger, false, 10.0, new Response(200, [], '{}'))
        ->get('https://mkg.example.com/mkg/web/v3/MKG/Documents/debi');
});

it('warns about a slow call even when logging is off', function () {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message): bool => $message === 'MKG request was slow.');

    // Drempel op 0 seconden, zodat elke aanroep als traag geldt.
    clientWithLogger($logger, false, 0.0, new Response(200, [], '{}'))
        ->get('https://mkg.example.com/mkg/web/v3/MKG/Documents/debi');
});

it('does nothing at all without a logger', function () {
    $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{}')]));
    $stack->push((new RequestLogger(null, true, 0.0))->middleware());

    $response = (new Client(['handler' => $stack]))
        ->get('https://mkg.example.com/mkg/web/v3/MKG/Documents/debi');

    expect($response->getStatusCode())->toBe(200);
});

it('logs a failed call as a warning, with the status when a response arrived', function (Closure $makeReason, ?int $expectedStatus) {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'MKG request failed.'
            && $context['status'] === $expectedStatus);

    $request = new Request('GET', 'https://mkg.example.com/mkg/web/v3/MKG/Documents/debi');
    $stack = HandlerStack::create(new MockHandler([$makeReason($request)]));
    $stack->push((new RequestLogger($logger, false, 10.0))->middleware());

    // Guzzle 8 moved getResponse() from RequestException to ResponseException; the rejection path must work on both.
    expect(fn () => (new Client(['handler' => $stack]))->send($request))->toThrow(RequestException::class);
})->with([
    'no response' => [fn (Request $request) => new RequestException('Transfer failed', $request), null],
    'with a response' => [fn (Request $request) => new ServerException('Server error', $request, new Response(503)), 503],
]);
