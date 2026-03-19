<?php

namespace Darvis\MkgClient\Config;

use Darvis\MkgClient\Contracts\ConfigProviderInterface;

class EnvConfigProvider implements ConfigProviderInterface
{
    /**
     * @var array<string, string>
     */
    private const ENV_KEYS = [
        'mkg.url_auth' => 'MKG_URL_AUTH',
        'mkg.url_prod' => 'MKG_URL_PROD',
        'mkg.customer' => 'MKG_CUSTOMER',
        'mkg.username' => 'MKG_USERNAME',
        'mkg.password' => 'MKG_PASSWORD',
        'mkg.verify_ssl' => 'MKG_VERIFY_SSL',
        'mkg.timeout' => 'MKG_TIMEOUT',
        'mkg.connect_timeout' => 'MKG_CONNECT_TIMEOUT',
        'mkg.cookie_storage_path' => 'MKG_COOKIE_STORAGE_PATH',
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        $envKey = self::ENV_KEYS[$key] ?? null;

        if ($envKey === null) {
            return $default;
        }

        $value = $_ENV[$envKey] ?? getenv($envKey);

        return $value === false ? $default : $value;
    }
}
