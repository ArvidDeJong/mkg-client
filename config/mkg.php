<?php

/**
 * MKG client connection settings.
 */
return [
    // Example: https://api.mkg.nl/restapi/auth
    'url_auth' => env('MKG_URL_AUTH', null),

    // Example: https://api.mkg.nl/restapi
    'url_prod' => env('MKG_URL_PROD', null),

    // MKG customer code (tenant identifier).
    'customer' => env('MKG_CUSTOMER', null),

    // MKG API username.
    'username' => env('MKG_USERNAME', null),

    // MKG API password.
    'password' => env('MKG_PASSWORD', null),

    // Whether TLS certificates should be verified (recommended: true in production).
    'verify_ssl' => (bool) env('MKG_VERIFY_SSL', true),

    // HTTP request timeout in seconds.
    'timeout' => (float) env('MKG_TIMEOUT', 30),

    // HTTP connection timeout in seconds.
    'connect_timeout' => (float) env('MKG_CONNECT_TIMEOUT', 10),

    // Relative storage path where the MKG JSESSIONID is cached.
    'cookie_storage_path' => env('MKG_COOKIE_STORAGE_PATH', 'mkg/cookie.txt'),
];
