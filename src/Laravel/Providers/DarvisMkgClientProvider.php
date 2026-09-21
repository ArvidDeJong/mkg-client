<?php

namespace Darvis\MkgClient\Laravel\Providers;

use Darvis\MkgClient\BaseMkgService;
use Darvis\MkgClient\Services\AddressesService;
use Darvis\MkgClient\Services\ArticleService;
use Darvis\MkgClient\Services\ContactpersonService;
use Darvis\MkgClient\Services\DebtorsService;
use Darvis\MkgClient\Services\OrdersService;
use Darvis\MkgClient\Services\RelationsService;
use Darvis\MkgClient\Services\UserService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class DarvisMkgClientProvider extends ServiceProvider
{
    /**
     * Every service the package ships.
     *
     * @var array<int, class-string<BaseMkgService>>
     */
    private const SERVICES = [
        AddressesService::class,
        ArticleService::class,
        ContactpersonService::class,
        DebtorsService::class,
        OrdersService::class,
        RelationsService::class,
        UserService::class,
    ];

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../../config/mkg.php' => config_path('mkg.php'),
            ], 'mkg-config');

            $this->publishes([
                __DIR__.'/../../../resources/csv' => storage_path('mkg'),
            ], 'mkg-csv');

            $this->publishes([
                __DIR__.'/../../../config/mkg.php' => config_path('mkg.php'),
                __DIR__.'/../../../resources/csv' => storage_path('mkg'),
            ], 'mkg-client');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../../config/mkg.php',
            'mkg'
        );

        $this->bindServices();
    }

    /**
     * Bind every service, so the container never builds its constructor arguments by itself.
     *
     * The constructor takes an optional Guzzle client. Laravel 11 builds an instantiable class for
     * an optional argument instead of using its null default, so an unbound service got a bare
     * client there: no timeout, redirects followed, no request log and `verify_ssl` ignored. With
     * a binding the service creates the client itself, from the package config, on every Laravel
     * version. Not a singleton: every resolve gives a new service, as it always did.
     */
    private function bindServices(): void
    {
        foreach (self::SERVICES as $service) {
            $this->app->bind($service, fn (Application $app) => new $service(
                logger: $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null,
            ));
        }
    }
}
