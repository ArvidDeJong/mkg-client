<?php

use Darvis\MkgClient\Tests\Fixtures\TestableMkgService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

function makeTestableService(): TestableMkgService
{
    $client = new Client([
        'handler' => HandlerStack::create(new MockHandler([])),
        'http_errors' => true,
    ]);

    return new TestableMkgService($client);
}

test('it normalizes integer values', function (): void {
    $service = makeTestableService();

    expect($service->normalizeTypedValue('42', 'integer'))->toBe(42);
    expect($service->normalizeTypedValue(7.0, 'integer'))->toBe(7);
    expect($service->normalizeTypedValue('not-a-number', 'integer'))->toBe('not-a-number');
});

test('it normalizes decimal values', function (): void {
    $service = makeTestableService();

    expect($service->normalizeTypedValue('12,50', 'decimal'))->toBe(12.5);
    expect($service->normalizeTypedValue('1.234,56', 'bedrag'))->toBe(1234.56);
    expect($service->normalizeTypedValue('19.75', 'percentage'))->toBe(19.75);
});

test('it normalizes logical values', function (): void {
    $service = makeTestableService();

    expect($service->normalizeTypedValue('WAAR', 'logical'))->toBeTrue();
    expect($service->normalizeTypedValue('onwaar', 'logical'))->toBeFalse();
    expect($service->normalizeTypedValue('ja', 'logical'))->toBeTrue();
    expect($service->normalizeTypedValue('nee', 'logical'))->toBeFalse();
    expect($service->normalizeTypedValue('unknown', 'logical'))->toBe('unknown');
});

test('it normalizes date values', function (): void {
    $service = makeTestableService();

    expect($service->normalizeTypedValue('18-03-2026', 'datum'))->toBe('2026-03-18');
    expect($service->normalizeTypedValue('2026-03-18', 'date'))->toBe('2026-03-18');
    expect($service->normalizeTypedValue('03/18/2026', 'date'))->toBe('03/18/2026');
});

test('it casts known character-like values to strings', function (): void {
    $service = makeTestableService();

    expect($service->normalizeTypedValue(123, 'character'))->toBe('123');
    expect($service->normalizeTypedValue(123, 'omschrijving'))->toBe('123');
    expect($service->normalizeTypedValue(123, 'memo'))->toBe('123');
    expect($service->normalizeTypedValue(123, 'email'))->toBe('123');
});

test('it normalizes row values based on metadata map', function (): void {
    $service = makeTestableService();

    $rows = [[
        'qty' => '10',
        'price' => '2,75',
        'active' => 'waar',
        'date' => '18-03-2026',
        'note' => 123,
        'unchanged' => 'x',
    ]];

    $meta = [
        'qty' => ['label' => 'Quantity', 'type' => 'integer', 'isDatabaseField' => true],
        'price' => ['label' => 'Price', 'type' => 'decimal', 'isDatabaseField' => true],
        'active' => ['label' => 'Active', 'type' => 'logical', 'isDatabaseField' => true],
        'date' => ['label' => 'Date', 'type' => 'datum', 'isDatabaseField' => true],
        'note' => ['label' => 'Note', 'type' => 'memo', 'isDatabaseField' => false],
    ];

    $normalized = $service->normalizeTypedRows($rows, $meta);

    expect($normalized[0]['qty'])->toBe(10);
    expect($normalized[0]['price'])->toBe(2.75);
    expect($normalized[0]['active'])->toBeTrue();
    expect($normalized[0]['date'])->toBe('2026-03-18');
    expect($normalized[0]['note'])->toBe('123');
    expect($normalized[0]['unchanged'])->toBe('x');
});
