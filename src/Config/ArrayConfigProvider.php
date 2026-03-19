<?php

namespace Darvis\MkgClient\Config;

use Darvis\MkgClient\Contracts\ConfigProviderInterface;

class ArrayConfigProvider implements ConfigProviderInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }

        return $default;
    }
}
