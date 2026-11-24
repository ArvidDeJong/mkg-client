<?php

use Darvis\MkgClient\Config\ArrayConfigProvider;
use Darvis\MkgClient\Contracts\CookieStoreInterface;
use Darvis\MkgClient\Services\PackageListsService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * @param  array<int, array<string, mixed>>  $history  filled with the requests the service sends
 * @param  array<int|string, mixed>  $rows  the rows MKG answers with
 * @param  string  $document  the MKG document the rows are returned under
 */
function makePackageListsService(array &$history, array $rows, string $document = 'pkbh'): PackageListsService
{
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
            'response' => ['ResultData' => [[$document => $rows]]],
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

    return new PackageListsService(
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
function sentPackageListQuery(array $history): array
{
    parse_str($history[0]['request']->getUri()->getQuery(), $query);

    return $query;
}

it('asks for the package list header fields when no field list is given', function (): void {
    $history = [];
    $service = makePackageListsService($history, []);

    $service->listHeaders(filter: 'sys_dat_wijzig = 2026-11-24', numRows: 1000, sort: 'pkbh_num', skipRows: 0);

    $query = sentPackageListQuery($history);

    expect($history[0]['request']->getUri()->getPath())->toBe('/restapi/pkbh')
        ->and(explode(',', $query['FieldList']))->toBe(PackageListsService::DEFAULT_PACKAGE_LIST_FIELD_LIST)
        ->and($query['Filter'])->toBe('sys_dat_wijzig = 2026-11-24')
        ->and($query['NumRows'])->toBe('1000')
        ->and($query['Sort'])->toBe('pkbh_num')
        ->and($query['SkipRows'])->toBe('0');
});

it('returns the flat header of one package list', function (): void {
    $history = [];
    // MKG returns a single row as an object instead of a list.
    $service = makePackageListsService($history, ['pkbh_num' => 'VP2604512', 'debi_num' => '10001']);

    $rows = $service->findHeaderRowsByPackageListNumber('VP2604512');

    $query = sentPackageListQuery($history);

    expect($rows)->toBe([['pkbh_num' => 'VP2604512', 'debi_num' => '10001']])
        ->and($query['Filter'])->toBe('pkbh_num = VP2604512')
        ->and($query['NumRows'])->toBe('1');
});

it('asks for the article, descriptions and quantities of the rows when no field list is given', function (): void {
    $history = [];
    $service = makePackageListsService($history, [], 'pkbr');

    $service->listRows();

    $query = sentPackageListQuery($history);

    // Without a FieldList MKG only returns admi_num, pkbh_num and pkbr_num.
    expect($history[0]['request']->getUri()->getPath())->toBe('/restapi/pkbr')
        ->and(explode(',', $query['FieldList']))->toContain(
            'vorh_num',
            'arti_code',
            'pkbr_oms_1',
            'pkbr_aantal_verkoop',
            'pkbr_aantal_pakbon',
            'pkbr_aantal_backorder',
            'pkbr_eenh_order',
            'pkbr_referentie',
        );
});

it('sends the field list of the caller as written', function (): void {
    $history = [];
    $service = makePackageListsService($history, [], 'pkbr');

    $service->findRowsByPackageListNumber('VP2604512', ['pkbh_num', 'pkbr_memo_extern']);

    expect(sentPackageListQuery($history)['FieldList'])->toBe('pkbh_num,pkbr_memo_extern');
});

it('returns the flat rows of one package list in row number order', function (): void {
    $history = [];
    $rows = [
        ['pkbh_num' => 'VP2604512', 'pkbr_num' => 1, 'arti_code' => 'DT6310', 'pkbr_aantal_pakbon' => 4.5],
        ['pkbh_num' => 'VP2604512', 'pkbr_num' => 2, 'arti_code' => 'DT6320', 'pkbr_aantal_pakbon' => 2.5],
    ];
    $service = makePackageListsService($history, $rows, 'pkbr');

    $found = $service->findLineRowsByPackageListNumber('VP2604512');

    $query = sentPackageListQuery($history);

    expect($found)->toBe($rows)
        ->and($query['Filter'])->toBe('pkbh_num = VP2604512')
        ->and($query['Sort'])->toBe('pkbr_num')
        // Without NumRows MKG returns 100 rows and gives no sign that more exist.
        ->and($query['NumRows'])->toBe('1000');
});

it('returns the package list rows of one sales order', function (): void {
    $history = [];
    $rows = [['pkbh_num' => 'VP2604512', 'vorh_num' => 'VK2606096', 'pkbr_num' => 1]];
    $service = makePackageListsService($history, $rows, 'pkbr');

    $found = $service->findLineRowsBySalesOrderNumber('VK2606096');

    $query = sentPackageListQuery($history);

    expect($found)->toBe($rows)
        ->and($query['Filter'])->toBe('vorh_num = VK2606096')
        ->and($query['Sort'])->toBe('pkbh_num')
        ->and($query['NumRows'])->toBe('1000');
});

it('returns no rows when MKG finds no package list', function (): void {
    $history = [];
    $service = makePackageListsService($history, [], 'pkbr');

    expect($service->findLineRowsByPackageListNumber('VP0000000'))->toBe([]);
});

it('quotes a package list number that could extend the filter', function (): void {
    $history = [];
    $service = makePackageListsService($history, [], 'pkbr');

    $service->findRowsByPackageListNumber('VP1 or pkbh_num <> ""');

    expect(sentPackageListQuery($history)['Filter'])->toBe('pkbh_num = "VP1 or pkbh_num <> \"\""');
});

it('refuses an empty number before any request', function (string $method): void {
    $history = [];
    $service = makePackageListsService($history, []);

    expect(fn () => $service->{$method}(''))->toThrow(InvalidArgumentException::class)
        ->and($history)->toBe([]);
})->with([
    'header' => 'findHeaderByPackageListNumber',
    'rows of a package list' => 'findRowsByPackageListNumber',
    'rows of a sales order' => 'findRowsBySalesOrderNumber',
]);
