<?php

use Darvis\MkgClient\Config\LaravelConfigProvider;
use Darvis\MkgClient\Support\MkgEndpoints;

/**
 * The MKG base URL used to be hand-typed per installation, which is how it
 * breaks: a stale `/mkg/rest/v1` or a `/mkg/rest/v3` typo is answered with a
 * 403 and an HTML error page and stops every call.
 */
function endpoints(): MkgEndpoints
{
    return new MkgEndpoints(new LaravelConfigProvider);
}

it('builds both urls from the host', function () {
    config()->set('mkg.host', 'mkg.example.com');
    config()->set('mkg.url_prod', null);
    config()->set('mkg.url_auth', null);

    expect(endpoints()->rest())->toBe('https://mkg.example.com/mkg/web/v3/MKG/Documents')
        ->and(endpoints()->auth())->toBe('https://mkg.example.com/mkg/static/auth/j_spring_security_check');
});

it('never builds a retired rest path', function () {
    config()->set('mkg.host', 'mkg.example.com');
    config()->set('mkg.url_prod', null);

    expect(endpoints()->rest())->not->toContain('/mkg/rest/');
});

it('accepts a host that was given with a scheme or trailing slash', function (string $host) {
    config()->set('mkg.host', $host);
    config()->set('mkg.url_prod', null);

    expect(endpoints()->rest())->toBe('https://mkg.example.com/mkg/web/v3/MKG/Documents');
})->with([
    'plain' => 'mkg.example.com',
    'with scheme' => 'https://mkg.example.com',
    'with trailing slash' => 'mkg.example.com/',
    'with both' => 'https://mkg.example.com/',
]);

it('builds the training environment url from the client path', function () {
    config()->set('mkg.host', 'saas1-oefen.mkg.eu:443');
    config()->set('mkg.client_path', 'mkgoefenclient');
    config()->set('mkg.url_prod', null);
    config()->set('mkg.url_auth', null);

    expect(endpoints()->rest())->toBe('https://saas1-oefen.mkg.eu:443/mkgoefenclient/web/v3/MKG/Documents')
        ->and(endpoints()->auth())->toBe('https://saas1-oefen.mkg.eu:443/mkgoefenclient/static/auth/j_spring_security_check');
});

it('keeps a port in the host', function () {
    config()->set('mkg.host', 'https://mkgapi.yourdomain.local:443');
    config()->set('mkg.client_path', null);
    config()->set('mkg.url_prod', null);

    expect(endpoints()->rest())->toBe('https://mkgapi.yourdomain.local:443/mkg/web/v3/MKG/Documents');
});

it('keeps honouring an explicitly configured url', function () {
    // Installations that deviate from the standard layout must keep working.
    config()->set('mkg.host', 'mkg.example.com');
    config()->set('mkg.url_prod', 'https://legacy.example.com/custom/base/');
    config()->set('mkg.url_auth', 'https://legacy.example.com/custom/auth');

    expect(endpoints()->rest())->toBe('https://legacy.example.com/custom/base')
        ->and(endpoints()->auth())->toBe('https://legacy.example.com/custom/auth');
});

it('says what to configure when neither host nor url is set', function () {
    config()->set('mkg.host', null);
    config()->set('mkg.url_prod', null);

    expect(fn () => endpoints()->rest())
        ->toThrow(RuntimeException::class, 'mkg.host');
});
