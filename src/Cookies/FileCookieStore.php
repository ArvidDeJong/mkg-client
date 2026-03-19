<?php

namespace Darvis\MkgClient\Cookies;

use Darvis\MkgClient\Contracts\CookieStoreInterface;

class FileCookieStore implements CookieStoreInterface
{
    public function read(string $path): ?string
    {
        $absolutePath = $this->resolvePath($path);

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        $content = file_get_contents($absolutePath);

        if (! is_string($content)) {
            return null;
        }

        $cookie = trim($content);

        return $cookie !== '' ? $cookie : null;
    }

    public function write(string $path, string $value): void
    {
        $absolutePath = $this->resolvePath($path);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($absolutePath, $value);
    }

    public function delete(string $path): void
    {
        $absolutePath = $this->resolvePath($path);

        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return $this->defaultCookiePath();
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        if (function_exists('storage_path')) {
            return storage_path('app/'.ltrim($path, '/'));
        }

        return rtrim(sys_get_temp_dir(), '/').'/'.ltrim($path, '/');
    }

    private function defaultCookiePath(): string
    {
        if (function_exists('storage_path')) {
            return storage_path('app/mkg/cookie.txt');
        }

        return rtrim(sys_get_temp_dir(), '/').'/mkg/cookie.txt';
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
    }
}
