<?php

use Darvis\MkgClient\Services\AddressesService;
use Darvis\MkgClient\Services\OrdersService;
use Darvis\MkgClient\Services\RelationsService;
use Psr\Http\Message\RequestInterface;

/**
 * @param  array<int, array<string, mixed>>  $history
 */
function sentTarget(array $history): string
{
    /** @var RequestInterface $request */
    $request = $history[0]['request'];

    return $request->getRequestTarget();
}

it('builds the same url as before for ordinary keys', function (): void {
    $history = [];
    $orders = new OrdersService(mkgRecordingClient([mkgEmptyEnvelope(), mkgEmptyEnvelope()], $history), mkgTestConfig(), mkgTestCookieStore());

    $orders->getHeaderByPrimaryKey(1, 'VK2606096');
    $orders->getRowByPrimaryKey('1', '500123', 10);

    expect((string) $history[0]['request']->getUri())->toBe('https://example.test/restapi/vorh/1+VK2606096');
    expect((string) $history[1]['request']->getUri())->toBe('https://example.test/restapi/vorr/1+500123+10');
});

it('keeps the plus of a composite primary key and a leading slash out of the way', function (): void {
    $history = [];
    $relations = new RelationsService(mkgRecordingClient([mkgEmptyEnvelope(), mkgEmptyEnvelope()], $history), mkgTestConfig(), mkgTestCookieStore());
    $addresses = new AddressesService(mkgRecordingClient([mkgEmptyEnvelope()], $history), mkgTestConfig(), mkgTestCookieStore());

    $relations->getByPrimaryKey('/1+10001');
    $relations->findByDebtorNumber(10001, ['rela_num']);
    $addresses->getByPrimaryKey('1+10001+2', 'adrs');

    expect((string) $history[0]['request']->getUri())->toBe('https://example.test/restapi/rela/1+10001');
    expect((string) $history[1]['request']->getUri())->toBe('https://example.test/restapi/rela/rela_debi/10001?FieldList=rela_num');
    expect((string) $history[2]['request']->getUri())->toBe('https://example.test/restapi/adrs/1+10001+2');
});

it('encodes a key so that it cannot change the request target', function (Closure $call, string $expected): void {
    $history = [];
    $arguments = [mkgRecordingClient([mkgEmptyEnvelope()], $history), mkgTestConfig(), mkgTestCookieStore()];

    $call($arguments);

    expect(sentTarget($history))->toBe($expected);
})->with([
    'order header' => [
        fn (array $a) => (new OrdersService(...$a))->getHeaderByPrimaryKey(1, '5/../../debi?NumRows=1000#'),
        '/restapi/vorh/1+5%2F..%2F..%2Fdebi%3FNumRows%3D1000%23',
    ],
    'order row' => [
        fn (array $a) => (new OrdersService(...$a))->getRowByPrimaryKey(1, 5, '1+2?x=1'),
        '/restapi/vorr/1+5+1%2B2%3Fx%3D1',
    ],
    'relation' => [
        fn (array $a) => (new RelationsService(...$a))->getByPrimaryKey('1/../../debi?x=1'),
        '/restapi/rela/1%2F..%2F..%2Fdebi%3Fx%3D1',
    ],
    'relation by debtor' => [
        fn (array $a) => (new RelationsService(...$a))->findByDebtorNumber('1?x=1', ['rela_num']),
        '/restapi/rela/rela_debi/1%3Fx%3D1?FieldList=rela_num',
    ],
    'address' => [
        fn (array $a) => (new AddressesService(...$a))->getByPrimaryKey('1?x=1'),
        '/restapi/adrs/1%3Fx%3D1',
    ],
    'address document' => [
        fn (array $a) => (new AddressesService(...$a))->getByPrimaryKey('1', 'adrs/../debi'),
        '/restapi/adrs%2F..%2Fdebi/1',
    ],
]);

it('rejects an empty or dot segment before any request is sent', function (Closure $call): void {
    $history = [];
    $arguments = [mkgRecordingClient([], $history), mkgTestConfig(), mkgTestCookieStore()];

    expect(fn () => $call($arguments))->toThrow(InvalidArgumentException::class);
    expect($history)->toBe([]);
})->with([
    'empty key' => [fn (array $a) => (new RelationsService(...$a))->getByPrimaryKey('')],
    'only a slash' => [fn (array $a) => (new RelationsService(...$a))->getByPrimaryKey('/')],
    'dot' => [fn (array $a) => (new RelationsService(...$a))->getByPrimaryKey('.')],
    'dot dot' => [fn (array $a) => (new RelationsService(...$a))->getByPrimaryKey('..')],
    'dot dot debtor' => [fn (array $a) => (new RelationsService(...$a))->findByDebtorNumber('..')],
    'dot dot document' => [fn (array $a) => (new AddressesService(...$a))->getByPrimaryKey('1', '..')],
    'dot dot list document' => [fn (array $a) => (new AddressesService(...$a))->list([], null, 1, '..')],
    'empty order number' => [fn (array $a) => (new OrdersService(...$a))->getHeaderByPrimaryKey(1, '')],
]);
