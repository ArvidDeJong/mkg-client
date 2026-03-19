<?php

use Darvis\MkgClient\Tests\Fixtures\TestableMkgService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

test('it prefers app storage csv over package csv when available', function (): void {
    $overridePath = storage_path('mkg/debi.csv');
    $overrideDirectory = dirname($overridePath);

    if (! is_dir($overrideDirectory)) {
        mkdir($overrideDirectory, 0777, true);
    }

    file_put_contents($overridePath, "veldnaam;label\n");

    $client = new Client([
        'handler' => HandlerStack::create(new MockHandler([])),
        'http_errors' => true,
    ]);

    $service = new TestableMkgService($client);

    $resolvedPath = $service->resolvePackageCsvPath('debi');

    expect($resolvedPath)->toBe($overridePath);
});

test('it falls back to bundled package csv when app override does not exist', function (): void {
    $overridePath = storage_path('mkg/debi.csv');

    if (is_file($overridePath)) {
        unlink($overridePath);
    }

    $client = new Client([
        'handler' => HandlerStack::create(new MockHandler([])),
        'http_errors' => true,
    ]);

    $service = new TestableMkgService($client);

    $resolvedPath = $service->resolvePackageCsvPath('debi');

    expect($resolvedPath)->toEndWith('/resources/csv/debi.csv');
    expect(is_file($resolvedPath))->toBeTrue();
});
