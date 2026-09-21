---
title: "Testing"
description: "Test Laravel code that uses darvis/mkg-client without an MKG installation: a Guzzle MockHandler answers the requests, with a complete Pest example."
nav_order: 5
---

# Testing

Never call an MKG installation from a test: it is a company's live ERP. This page shows how to test your own code while a fake answers the requests.

## Why Http::fake() does not work

The package sends its requests with Guzzle directly, not with Laravel's HTTP client. `Http::fake()` therefore never sees them, and an unfaked test would call the real host. Replace the Guzzle client instead: every service takes one as its first constructor argument, and Guzzle's `MockHandler` answers from a queue of responses you prepare.

## A complete example

The test binds `DebtorsService` in the container to a service with a fake client, so the code under test keeps calling `app(DebtorsService::class)` or type-hinting it.

File: `tests/Feature/MkgDebtorLookupTest.php` (Pest; the same calls work in a PHPUnit test method)

```php
<?php

use Darvis\MkgClient\Exceptions\MkgHttpException;
use Darvis\MkgClient\Services\DebtorsService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // The constructor throws when one of these is empty.
    config([
        'mkg.host' => 'mkg.test',
        'mkg.customer' => 'test-customer',
        'mkg.username' => 'test-user',
        'mkg.password' => 'test-password',
    ]);

    // The session cookie lives on the default disk. With one in place no login call is made.
    Storage::fake();
    Storage::put('mkg/cookie.txt', 'JSESSIONID=test');
});

/**
 * Binds DebtorsService to a Guzzle client that answers from a queue.
 *
 * @param  array<int, Response>  $responses
 * @param  array<int, array<string, mixed>>  $history
 */
function fakeMkg(array $responses, array &$history): void
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    app()->bind(DebtorsService::class, fn () => new DebtorsService(
        client: new Client(['handler' => $stack, 'allow_redirects' => false]),
    ));
}

it('finds a debtor by number', function () {
    $history = [];

    fakeMkg([
        new Response(200, [], json_encode(['response' => ['ResultData' => [['debi' => [
            ['debi_num' => '10001', 'debi_naam' => 'Example BV', 'debi_actief' => 'WAAR'],
        ]]]]])),
    ], $history);

    $rows = app(DebtorsService::class)->findDebtorRowsByDebtorNumber(10001);

    expect($rows)->toHaveCount(1);
    expect($rows[0]['debi_num'])->toBe('10001');
    expect($rows[0]['debi_actief'])->toBeTrue();

    // What went over the wire.
    parse_str($history[0]['request']->getUri()->getQuery(), $query);
    expect($query['Filter'])->toBe('debi_num = 10001');
});

it('returns no rows when MKG finds nothing', function () {
    $history = [];

    fakeMkg([new Response(200, [], '{"response":{"ResultData":[{}]}}')], $history);

    expect(app(DebtorsService::class)->findDebtorRowsByDebtorNumber(99999))->toBe([]);
});

it('throws when a login page answers instead of MKG', function () {
    $history = [];

    fakeMkg([new Response(200, ['Content-Type' => 'text/html'], '<html>Sign in</html>')], $history);

    app(DebtorsService::class)->findDebtorRowsByDebtorNumber(10001);
})->throws(MkgHttpException::class);
```

The first test queues one MKG answer, calls the finder and checks the converted row: `debi_actief` arrived as `WAAR` and is `true`. `Middleware::history()` records every request, so the test can also check the filter that was sent. In a real test, replace the direct call with the controller, command or job you want to test.

## Which responses to queue

Queue one response per request, in the order the requests are sent. An empty queue makes Guzzle throw an `OutOfBoundsException`, which tells you that your code sent more requests than you expected.

| Situation | Queue |
| --- | --- |
| Rows | `new Response(200, [], json_encode(['response' => ['ResultData' => [['debi' => [[…], […]]]]]]))`, with the document name (`debi`, `vorh`, …) as the key |
| No rows | `new Response(200, [], '{"response":{"ResultData":[{}]}}')` |
| No cookie in storage | First the login: `new Response(200, ['Set-Cookie' => 'JSESSIONID=test; Path=/mkg; HttpOnly'])`, then the answer |
| Expired session | `new Response(401, [], '{"status_code":401,"status_txt":"Not authenticated"}')`, then the login response, then the answer. Expect three requests in the history |
| Wrong URL path | `new Response(403, [], '<!doctype html><html></html>')`. Expect `MkgHttpException` and one request in the history: a `403` is not retried |
| Redirect or login page | `new Response(302, ['Location' => '/login'])` or `new Response(200, [], '<html></html>')`. Expect `MkgHttpException` |
| MKG is down | `new Response(500)`. Expect Guzzle's `ServerException` |

The combined searches send more than one request. `findDebtorRowsByNumberNameOrEmail()` and `findContactpersonRowsByNumberNameOrEmail()` send two for an input of digits; `findArticleRowsByCodeOrName()` and `findUserRowsByCodeOrName()` always send two.

The `4xx` and `5xx` rows rely on Guzzle's `http_errors` option, which is on by default. Do not switch it off in the fake client.

## Things that go wrong in a test

- **`RuntimeException: Missing MKG configuration values`**: the config is set after the service was created. Set it in `beforeEach()`, before anything resolves a service.
- **The first queued response is used up by a login**: no cookie was stored. Call `Storage::fake()` and `Storage::put('mkg/cookie.txt', 'JSESSIONID=test')` first. The value must start with `JSESSIONID=`, or it is ignored.
- **Values are not converted**: the raw `list…()` and `find…()` methods return MKG's response as it is. Use a `find…Rows…()` method or `extract…Rows()`.
