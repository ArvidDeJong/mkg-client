<?php

use Darvis\MkgClient\Config\ArrayConfigProvider;
use Darvis\MkgClient\Contracts\CookieStoreInterface;
use Darvis\MkgClient\Services\ProductionOrdersService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * @param  array<int, array<string, mixed>>  $history  filled with the requests the service sends
 * @param  array<int|string, mixed>  $prdh  the rows MKG answers with
 */
function makeProductionOrdersService(array &$history, array $prdh): ProductionOrdersService
{
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
            'response' => ['ResultData' => [['prdh' => $prdh]]],
        ])),
    ]));
    $stack->push(Middleware::history($history));

    $cookieStore = new class implements CookieStoreInterface
    {
        public function read(string $path): ?string
        {
            return 'JSESSIONID=existing';
        }

        public function write(string $path, string $value): void {}

        public function delete(string $path): void {}
    };

    return new ProductionOrdersService(
        new Client(['handler' => $stack, 'http_errors' => true]),
        new ArrayConfigProvider([
            'mkg.url_auth' => 'https://example.test/restapi/auth',
            'mkg.url_prod' => 'https://example.test/restapi',
            'mkg.customer' => 'DEMO',
            'mkg.username' => 'demo-user',
            'mkg.password' => 'demo-password',
        ]),
        $cookieStore,
    );
}

/**
 * @param  array<int, array<string, mixed>>  $history
 * @return array<string, mixed>
 */
function sentProductionOrderQuery(array $history): array
{
    parse_str($history[0]['request']->getUri()->getQuery(), $query);

    return $query;
}

it('asks for the linked sales order by name when no field list is given', function (): void {
    $history = [];
    $service = makeProductionOrdersService($history, []);

    $service->listHeaders(filter: 'sys_dat_wijzig = 2026-11-13', numRows: 1000, sort: 'prdh_num', skipRows: 0);

    $query = sentProductionOrderQuery($history);

    expect($history[0]['request']->getUri()->getPath())->toBe('/restapi/prdh')
        ->and(explode(',', $query['FieldList']))->toContain('prdh_num', 't_vorh_num', 't_klant')
        ->and($query['Filter'])->toBe('sys_dat_wijzig = 2026-11-13')
        ->and($query['NumRows'])->toBe('1000')
        ->and($query['Sort'])->toBe('prdh_num')
        ->and($query['SkipRows'])->toBe('0');
});

it('sends the field list of the caller as written', function (): void {
    $history = [];
    $service = makeProductionOrdersService($history, []);

    $service->listHeaders(['prdh_num', 'prdh_memo']);

    expect(sentProductionOrderQuery($history)['FieldList'])->toBe('prdh_num,prdh_memo');
});

it('returns the flat row of one production order with its sales order', function (): void {
    $history = [];
    // MKG returns a single row as an object instead of a list.
    $service = makeProductionOrdersService($history, ['prdh_num' => 'PO2600783', 't_vorh_num' => 'VK2605768']);

    $rows = $service->findHeaderRowsByProductionOrderNumber('PO2600783');

    $query = sentProductionOrderQuery($history);

    expect($rows)->toBe([['prdh_num' => 'PO2600783', 't_vorh_num' => 'VK2605768']])
        ->and($query['Filter'])->toBe('prdh_num = PO2600783')
        ->and($query['NumRows'])->toBe('1');
});

it('returns no rows when MKG finds no production order', function (): void {
    $history = [];
    $service = makeProductionOrdersService($history, []);

    expect($service->findHeaderRowsByProductionOrderNumber('PO0000000'))->toBe([]);
});

it('refuses an empty production order number before any request', function (): void {
    $history = [];
    $service = makeProductionOrdersService($history, []);

    expect(fn () => $service->findHeaderByProductionOrderNumber(''))->toThrow(InvalidArgumentException::class)
        ->and($history)->toBe([]);
});
