<?php

namespace Darvis\MkgClient\Laravel\Providers;

use Illuminate\Support\ServiceProvider;

class DarvisMkgClientProvider extends ServiceProvider
{
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
    }
}
