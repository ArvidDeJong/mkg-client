<?php

namespace Darvis\MkgClient\Contracts;

interface CookieStoreInterface
{
    public function read(string $path): ?string;

    public function write(string $path, string $value): void;

    public function delete(string $path): void;
}
