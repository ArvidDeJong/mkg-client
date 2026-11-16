<?php

use Darvis\MkgClient\Config\ArrayConfigProvider;
use Darvis\MkgClient\Services\DebtorsService;

/**
 * Guzzle 8 only accepts string header values; Guzzle 7 cast them. A customer id
 * that a config provider hands over as an int must still go out as text.
 */
it('sends a numeric customer id as a text header', function (): void {
    $history = [];
    $config = new ArrayConfigProvider([
        'mkg.url_auth' => 'https://example.test/restapi/auth',
        'mkg.url_prod' => 'https://example.test/restapi',
        'mkg.customer' => 1,
        'mkg.username' => 'demo-user',
        'mkg.password' => 'demo-password',
    ]);
    $service = new DebtorsService(mkgRecordingClient([mkgEmptyEnvelope()], $history), $config, mkgTestCookieStore());

    $service->findByDebtorNumber(10001);

    expect($history[0]['request']->getHeaderLine('X-CustomerID'))->toBe('1');
});
