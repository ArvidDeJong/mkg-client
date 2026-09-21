<?php

use Darvis\MkgClient\Services\ArticleService;
use Darvis\MkgClient\Services\DebtorsService;
use Darvis\MkgClient\Services\OrdersService;
use Darvis\MkgClient\Services\RelationsService;
use Psr\Http\Message\RequestInterface;

/**
 * @param  array<int, array<string, mixed>>  $history
 */
function sentFilter(array $history, int $index = 0): ?string
{
    /** @var RequestInterface $request */
    $request = $history[$index]['request'];

    parse_str($request->getUri()->getQuery(), $query);

    return is_string($query['Filter'] ?? null) ? $query['Filter'] : null;
}

it('sends a numeric key unquoted, exactly as before', function (string|int $value, string $expected): void {
    $history = [];
    $service = new DebtorsService(mkgRecordingClient([mkgEmptyEnvelope()], $history), mkgTestConfig(), mkgTestCookieStore());

    $service->findByDebtorNumber($value);

    expect(sentFilter($history))->toBe($expected);
})->with([
    'int' => [10001, 'debi_num = 10001'],
    'digits' => ['10001', 'debi_num = 10001'],
    'padded digits' => [' 10001 ', 'debi_num = 10001'],
    'negative' => ['-5', 'debi_num = -5'],
    'decimal' => ['12.50', 'debi_num = 12.50'],
]);

it('quotes a key that is not numeric instead of placing it in the filter as it is', function (): void {
    $history = [];
    $service = new OrdersService(mkgRecordingClient([mkgEmptyEnvelope()], $history), mkgTestConfig(), mkgTestCookieStore());

    $service->findHeaderByOrderNumber('VK2606096');

    expect(sentFilter($history))->toBe('vorh_num = "VK2606096"');
});

it('keeps a value with filter syntax inside the quoted value', function (string $method, string $service, string $field): void {
    $history = [];
    $instance = new $service(mkgRecordingClient([mkgEmptyEnvelope()], $history), mkgTestConfig(), mkgTestCookieStore());

    $instance->{$method}('1 or '.$field.' > 0');

    expect(sentFilter($history))->toBe($field.' = "1 or '.$field.' > 0"');
})->with([
    ['findHeaderByOrderNumber', OrdersService::class, 'vorh_num'],
    ['findRowsByOrderNumber', OrdersService::class, 'vorh_num'],
    ['findRowParametersByOrderNumber', OrdersService::class, 'vorh_num'],
    ['findByDebtorNumber', DebtorsService::class, 'debi_num'],
    ['findByRelationNumber', RelationsService::class, 'rela_num'],
]);

it('rejects an empty key before any request is sent', function (): void {
    $history = [];
    $service = new OrdersService(mkgRecordingClient([], $history), mkgTestConfig(), mkgTestCookieStore());

    expect(fn () => $service->findHeaderByOrderNumber('  '))->toThrow(InvalidArgumentException::class);
    expect($history)->toBe([]);
});

it('escapes the backslash before the quote in a text filter', function (string $input, string $expected): void {
    $history = [];
    $service = new ArticleService(mkgRecordingClient([mkgEmptyEnvelope()], $history), mkgTestConfig(), mkgTestCookieStore());

    $service->findByArticleCode($input);

    expect(sentFilter($history))->toBe('arti_code = "'.$expected.'"');
})->with([
    'normal value stays unchanged' => ['ABC-123.x', 'ABC-123.x'],
    'trailing backslash' => ['foo\\', 'foo\\\\'],
    'backslash and quote' => ['foo\\"', 'foo\\\\\\"'],
    'quote' => ['a"b', 'a\\"b'],
]);

it('escapes the backslash in a contains filter too', function (): void {
    $history = [];
    $service = new DebtorsService(mkgRecordingClient([mkgEmptyEnvelope()], $history), mkgTestConfig(), mkgTestCookieStore());

    $service->findByDebtorName('foo\\');

    expect(sentFilter($history))->toBe('debi_naam contains "foo\\\\"');
});

it('rejects a control character in a text filter before any request is sent', function (string $input): void {
    $history = [];
    $service = new ArticleService(mkgRecordingClient([], $history), mkgTestConfig(), mkgTestCookieStore());

    expect(fn () => $service->findByArticleCode($input))->toThrow(InvalidArgumentException::class);
    expect($history)->toBe([]);
})->with([
    'newline' => ["abc\ndef"],
    'nul' => ["abc\0def"],
    'carriage return' => ["abc\rdef"],
]);
