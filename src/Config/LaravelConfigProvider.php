<?php

namespace Darvis\MkgClient\Config;

use Darvis\MkgClient\Contracts\ConfigProviderInterface;

class LaravelConfigProvider implements ConfigProviderInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        if (! function_exists('config')) {
            return $default;
        }

        return config($key, $default);
    }
}
