<?php

use Darvis\MkgClient\Tests\Fixtures\TestableMkgService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

function makeBuildQueryService(): TestableMkgService
{
    return new TestableMkgService(new Client([
        'handler' => HandlerStack::create(new MockHandler([])),
        'http_errors' => true,
    ]));
}

test('buildListQuery includes Sort and SkipRows when provided', function (): void {
    $query = makeBuildQueryService()->callBuildListQuery(
        ['vorr_num', 'vorh_num'],
        'sys_dat_wijzig = 2026-06-30 AND sys_tijd_wijzig >= 1',
        1000,
        'sys_dat_wijzig,sys_tijd_wijzig,vorh_num,vorr_num',
        2000,
    );

    expect($query['FieldList'])->toBe('vorr_num,vorh_num');
    expect($query['Filter'])->toBe('sys_dat_wijzig = 2026-06-30 AND sys_tijd_wijzig >= 1');
    expect($query['NumRows'])->toBe(1000);
    expect($query['Sort'])->toBe('sys_dat_wijzig,sys_tijd_wijzig,vorh_num,vorr_num');
    expect($query['SkipRows'])->toBe(2000);
});

test('buildListQuery sends SkipRows=0 for the first page', function (): void {
    $query = makeBuildQueryService()->callBuildListQuery([], null, 1000, null, 0);

    expect($query)->toHaveKey('SkipRows');
    expect($query['SkipRows'])->toBe(0);
    expect($query)->not->toHaveKey('Sort');
});

test('buildListQuery omits SkipRows when null', function (): void {
    $query = makeBuildQueryService()->callBuildListQuery([], null, 1000);

    expect($query)->not->toHaveKey('SkipRows');
});
