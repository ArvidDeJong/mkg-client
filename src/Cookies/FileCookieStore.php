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

        // The cookie is a live ERP session: only the owner may enter the
        // directory or read the file. An existing directory is left as it is,
        // it may be a shared one such as the system temp directory.
        if (! is_dir($directory)) {
            @mkdir($directory, 0700, true);
        }

        // Written next to the target and renamed over it, so a reader never
        // sees a half written cookie and the file is never briefly world readable.
        $temporaryPath = @tempnam($directory, '.mkg-cookie-');

        // tempnam() falls back to the system temp directory when it cannot write here.
        if ($temporaryPath !== false && realpath(dirname($temporaryPath)) === realpath($directory)) {
            $this->restrictToOwner($temporaryPath);

            if (file_put_contents($temporaryPath, $value, LOCK_EX) !== false && @rename($temporaryPath, $absolutePath)) {
                $this->restrictToOwner($absolutePath);

                return;
            }
        }

        if ($temporaryPath !== false && is_file($temporaryPath)) {
            @unlink($temporaryPath);
        }

        // No temporary file could be made here: write in place, owner only.
        if (is_file($absolutePath)) {
            $this->restrictToOwner($absolutePath);
        }

        file_put_contents($absolutePath, $value, LOCK_EX);
        $this->restrictToOwner($absolutePath);
    }

    /**
     * Windows has no POSIX modes; chmod does nothing useful there, so a failure is not an error.
     */
    private function restrictToOwner(string $path): void
    {
        @chmod($path, 0600);
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
