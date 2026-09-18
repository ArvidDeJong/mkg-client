<?php

namespace Darvis\MkgClient\Config;

use Darvis\MkgClient\Contracts\ConfigProviderInterface;

class ChainConfigProvider implements ConfigProviderInterface
{
    /**
     * @param  ConfigProviderInterface[]  $providers
     */
    public function __construct(private readonly array $providers) {}

    public function get(string $key, mixed $default = null): mixed
    {
        foreach ($this->providers as $provider) {
            $value = $provider->get($key);

            if ($value !== null) {
                return $value;
            }
        }

        return $default;
    }
}
