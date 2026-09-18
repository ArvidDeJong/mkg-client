<?php

namespace Darvis\MkgClient\Cookies;

use Darvis\MkgClient\Contracts\CookieStoreInterface;
use Illuminate\Support\Facades\Storage;

class LaravelCookieStore implements CookieStoreInterface
{
    public function read(string $path): ?string
    {
        if (! Storage::exists($path)) {
            return null;
        }

        $cookie = trim((string) Storage::get($path));

        return $cookie !== '' ? $cookie : null;
    }

    public function write(string $path, string $value): void
    {
        Storage::put($path, $value);
    }

    public function delete(string $path): void
    {
        if (Storage::exists($path)) {
            Storage::delete($path);
        }
    }
}
