<?php

namespace Darvis\MkgClient\Contracts;

interface ConfigProviderInterface
{
    public function get(string $key, mixed $default = null): mixed;
}
