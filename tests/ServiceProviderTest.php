<?php

test('package config is loaded', function (): void {
    expect(config('mkg.url_auth'))->toBe('https://example.test/restapi/auth');
    expect(config('mkg.url_prod'))->toBe('https://example.test/restapi');
});

test('default optional config values exist', function (): void {
    expect(config('mkg.verify_ssl'))->toBeBool();
    expect((float) config('mkg.timeout'))->toBeFloat();
    expect((float) config('mkg.connect_timeout'))->toBeFloat();
    expect(config('mkg.cookie_storage_path'))->toBeString();
});
