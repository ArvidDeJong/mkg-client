<?php

/**
 * MKG client connection settings. Keys are in alphabetical order.
 */
return [
    // Client segment in the URL: 'mkg' for a normal installation,
    // 'mkgoefenclient' for a training environment.
    'client_path' => env('MKG_CLIENT_PATH', 'mkg'),

    // HTTP connection timeout in seconds.
    'connect_timeout' => (float) env('MKG_CONNECT_TIMEOUT', 10),

    // Relative storage path where the MKG JSESSIONID is cached.
    'cookie_storage_path' => env('MKG_COOKIE_STORAGE_PATH', 'mkg/cookie.txt'),

    // MKG customer code (tenant identifier).
    'customer' => env('MKG_CUSTOMER', null),

    // Hostname of the MKG installation, without scheme. Example: mkg.example.com
    // With this set, the client builds both URLs itself and there is nothing to mistype.
    'host' => env('MKG_HOST', null),

    // Log every MKG call (method, path, status, duration) at debug level. MKG
    // traffic uses plain Guzzle, so profilers that hook Laravel's HTTP client
    // never see it; switch this on to find where a sync stalls.
    'log_requests' => (bool) env('MKG_LOG_REQUESTS', false),

    // MKG API password.
    'password' => env('MKG_PASSWORD', null),

    // A call slower than this is logged as a warning even when log_requests is off.
    'slow_request_seconds' => (float) env('MKG_SLOW_REQUEST_SECONDS', 10),

    // HTTP request timeout in seconds.
    'timeout' => (float) env('MKG_TIMEOUT', 30),

    // Optional overrides for installations that deviate from the standard layout.
    // Leave empty to derive them from `host`.
    // Derived auth: https://{host}/mkg/static/auth/j_spring_security_check
    'url_auth' => env('MKG_URL_AUTH', null),

    // Derived REST base: https://{host}/mkg/web/v3/MKG/Documents
    // Note the path is `web/v3`; `rest/v3` and the retired `rest/v1` return 403.
    'url_prod' => env('MKG_URL_PROD', null),

    // MKG API username.
    'username' => env('MKG_USERNAME', null),

    // Whether TLS certificates should be verified (recommended: true in production).
    'verify_ssl' => (bool) env('MKG_VERIFY_SSL', true),
];
