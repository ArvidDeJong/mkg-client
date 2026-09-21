<?php

use Darvis\MkgClient\BaseMkgService;
use Darvis\MkgClient\Services\AddressesService;
use Darvis\MkgClient\Services\ArticleService;
use Darvis\MkgClient\Services\ContactpersonService;
use Darvis\MkgClient\Services\DebtorsService;
use Darvis\MkgClient\Services\OrdersService;
use Darvis\MkgClient\Services\RelationsService;
use Darvis\MkgClient\Services\UserService;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

/**
 * Laravel 11's container builds an instantiable class for an optional constructor argument instead
 * of using its null default. Without a binding, `app(DebtorsService::class)` therefore got a bare
 * Guzzle client there: no timeout, redirects followed, no request log, and `verify_ssl` ignored.
 * The provider binds every service, so the client is always the one the package configures.
 */
function mkgClientOf(BaseMkgService $service): Client
{
    $property = new ReflectionProperty(BaseMkgService::class, 'client');

    return $property->getValue($service);
}

it('gives a service from the container the client the package configures', function (string $service): void {
    config()->set('mkg.timeout', 12);
    config()->set('mkg.connect_timeout', 4);
    config()->set('mkg.verify_ssl', false);

    $client = mkgClientOf(app($service));

    expect($client->getConfig('timeout'))->toBe(12.0)
        ->and($client->getConfig('connect_timeout'))->toBe(4.0)
        ->and($client->getConfig('allow_redirects'))->toBeFalse()
        ->and($client->getConfig('verify'))->toBeFalse();
})->with([
    AddressesService::class,
    ArticleService::class,
    ContactpersonService::class,
    DebtorsService::class,
    OrdersService::class,
    RelationsService::class,
    UserService::class,
]);

it('builds a new service on every resolve, as before', function (): void {
    expect(app(DebtorsService::class))->not->toBe(app(DebtorsService::class));
});

it('still hands the application logger to a service from the container', function (): void {
    $property = new ReflectionProperty(BaseMkgService::class, 'logger');

    expect($property->getValue(app(DebtorsService::class)))->toBeInstanceOf(LoggerInterface::class);
});
