<?php

namespace Darvis\MkgClient\Support;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Guzzle middleware that reports how long each MKG call took.
 *
 * MKG traffic goes out over plain Guzzle, so profilers that hook Laravel's HTTP
 * client (Debugbar's http_client collector, for one) never see it: a request
 * that spends half a minute in MKG shows up as unaccounted time. That makes a
 * stalling sync very hard to place. This closes that gap for every consumer.
 *
 * A call slower than `mkg.slow_request_seconds` is logged as a warning even when
 * `mkg.log_requests` is off, so a production stall leaves a trace either way.
 */
final class RequestLogger
{
    public function __construct(
        private readonly ?LoggerInterface $logger,
        private readonly bool $logRequests = false,
        private readonly float $slowRequestSeconds = 10.0,
    ) {}

    /**
     * @return callable(callable): callable
     */
    public function middleware(): callable
    {
        return function (callable $handler): callable {
            return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
                if ($this->logger === null) {
                    return $handler($request, $options);
                }

                $startedAt = microtime(true);

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($request, $startedAt): ResponseInterface {
                        $this->report($request, $response->getStatusCode(), $startedAt);

                        return $response;
                    },
                    function ($reason) use ($request, $startedAt) {
                        $status = $reason instanceof RequestException && $reason->hasResponse()
                            ? $reason->getResponse()?->getStatusCode()
                            : null;

                        $this->report($request, $status, $startedAt, failed: true);

                        return Create::rejectionFor($reason);
                    }
                );
            };
        };
    }

    /**
     * A response that arrived but cannot be used: a redirect, or a 2xx whose
     * body is not JSON. Always a warning, also when `log_requests` is off,
     * because the caller gets an exception instead of rows.
     */
    public function unusableResponse(string $method, string $path, int $status, string $reason): void
    {
        $this->logger?->warning('MKG response was not usable.', [
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'reason' => $reason,
        ]);
    }

    private function report(RequestInterface $request, ?int $status, float $startedAt, bool $failed = false): void
    {
        $seconds = microtime(true) - $startedAt;

        $context = [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'status' => $status,
            'seconds' => round($seconds, 3),
        ];

        if ($failed || $seconds >= $this->slowRequestSeconds) {
            $this->logger?->warning($failed ? 'MKG request failed.' : 'MKG request was slow.', $context);

            return;
        }

        if ($this->logRequests) {
            $this->logger?->debug('MKG request completed.', $context);
        }
    }
}
