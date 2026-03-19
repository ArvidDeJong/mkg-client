<?php

namespace Darvis\MkgClient\Tests;

use Darvis\MkgClient\Laravel\Providers\DarvisMkgClientProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [DarvisMkgClientProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('mkg.url_auth', 'https://example.test/restapi/auth');
        $app['config']->set('mkg.url_prod', 'https://example.test/restapi');
        $app['config']->set('mkg.customer', 'DEMO');
        $app['config']->set('mkg.username', 'demo-user');
        $app['config']->set('mkg.password', 'demo-password');
    }
}
