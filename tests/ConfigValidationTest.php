<?php

use Darvis\MkgClient\Services\DebtorsService;

test('it throws a runtime exception when required mkg config is missing', function (): void {
    config()->set('mkg.url_auth', null);

    expect(fn () => app(DebtorsService::class))
        ->toThrow(RuntimeException::class, 'Missing MKG configuration values');
});
