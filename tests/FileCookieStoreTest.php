<?php

use Darvis\MkgClient\Cookies\FileCookieStore;

beforeEach(function (): void {
    $this->cookieRoot = sys_get_temp_dir().'/mkg-client-test-'.bin2hex(random_bytes(6));
    $this->previousUmask = umask(0022);
});

afterEach(function (): void {
    umask($this->previousUmask);

    foreach ((array) glob($this->cookieRoot.'/session/*') as $file) {
        @unlink((string) $file);
    }

    @rmdir($this->cookieRoot.'/session');
    @rmdir($this->cookieRoot);
});

it('creates the cookie file for the owner only', function (): void {
    $path = $this->cookieRoot.'/session/cookie.txt';

    (new FileCookieStore)->write($path, 'JSESSIONID=abc');

    clearstatcache();

    expect(fileperms($path) & 0777)->toBe(0600);
    expect(fileperms(dirname($path)) & 0777)->toBe(0700);
    expect(fileperms($this->cookieRoot) & 0777)->toBe(0700);
    expect((new FileCookieStore)->read($path))->toBe('JSESSIONID=abc');
})->skip(PHP_OS_FAMILY === 'Windows', 'Windows has no POSIX file modes.');

it('tightens an existing cookie file that was created too openly', function (): void {
    $path = $this->cookieRoot.'/session/cookie.txt';

    mkdir(dirname($path), 0755, true);
    file_put_contents($path, 'JSESSIONID=old');
    chmod($path, 0644);

    (new FileCookieStore)->write($path, 'JSESSIONID=new');

    clearstatcache();

    expect(fileperms($path) & 0777)->toBe(0600);
    expect((new FileCookieStore)->read($path))->toBe('JSESSIONID=new');
})->skip(PHP_OS_FAMILY === 'Windows', 'Windows has no POSIX file modes.');

it('replaces the cookie in one step and leaves no temporary file behind', function (): void {
    $path = $this->cookieRoot.'/session/cookie.txt';
    $store = new FileCookieStore;

    $store->write($path, 'JSESSIONID=first');
    $store->write($path, 'JSESSIONID=second');

    expect($store->read($path))->toBe('JSESSIONID=second');
    expect(array_map('basename', (array) glob(dirname($path).'/{,.}*', GLOB_BRACE)))
        ->toEqualCanonicalizing(['.', '..', 'cookie.txt']);

    $store->delete($path);

    expect($store->read($path))->toBeNull();
});
