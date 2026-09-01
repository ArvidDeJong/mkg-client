<?php

namespace Darvis\MkgClient\Support;

use Darvis\MkgClient\Contracts\ConfigProviderInterface;
use RuntimeException;

/**
 * Resolves the MKG endpoints from configuration.
 *
 * Across on-premise, MKG Cloud and training installations only two things vary:
 * the host (optionally with a port) and the client segment, which is `mkg` for a
 * normal install and `mkgoefenclient` for a training environment. Everything
 * after that is identical, so those paths live here as constants instead of in
 * every consumer's environment file.
 *
 * Hand-typing the base URL is how installations break: a stale `/mkg/rest/v1` or
 * a `/mkg/rest/v3` typo is answered by Tomcat with a 403 and an HTML error page,
 * which stops every call while looking like a permission problem.
 *
 * Set `mkg.host` (and `mkg.client_path` for a training environment) and the URLs
 * are built for you. `mkg.url_auth`/`mkg.url_prod` still win when set, for
 * installations that deviate from the standard layout.
 */
final class MkgEndpoints
{
    /** Client segment of a standard installation. */
    public const DEFAULT_CLIENT_PATH = 'mkg';

    /** REST base, relative to the client segment. Note: `web/v3`, not `rest/v3`. */
    public const REST_SUFFIX = '/web/v3/MKG/Documents';

    /** Tomcat FORM-authentication endpoint that hands out the JSESSIONID. */
    public const AUTH_SUFFIX = '/static/auth/j_spring_security_check';

    public function __construct(private readonly ConfigProviderInterface $config) {}

    /**
     * Absolute base URL for document requests, without a trailing slash.
     */
    public function rest(): string
    {
        return $this->resolve('mkg.url_prod', self::REST_SUFFIX);
    }

    /**
     * Absolute URL of the authentication endpoint.
     */
    public function auth(): string
    {
        return $this->resolve('mkg.url_auth', self::AUTH_SUFFIX);
    }

    /**
     * True when the endpoints can be resolved, so configuration can be
     * validated before a request is attempted.
     */
    public function isConfigured(): bool
    {
        return $this->stringConfig('mkg.url_prod') !== null
            || $this->stringConfig('mkg.url_auth') !== null
            || $this->host() !== null;
    }

    private function resolve(string $key, string $suffix): string
    {
        $explicit = $this->stringConfig($key);

        if ($explicit !== null) {
            return rtrim($explicit, '/');
        }

        $host = $this->host();

        if ($host === null) {
            throw new RuntimeException(
                'Missing MKG configuration values: set mkg.host (for example "saas1.mkg.eu"), '
                .'or set '.$key.' explicitly.'
            );
        }

        return 'https://'.$host.'/'.$this->clientPath().$suffix;
    }

    /**
     * The configured host, stripped of scheme and slashes. A port is kept.
     */
    private function host(): ?string
    {
        $host = $this->stringConfig('mkg.host');

        if ($host === null) {
            return null;
        }

        $host = preg_replace('#^https?://#i', '', $host) ?? $host;

        return trim($host, '/') ?: null;
    }

    /**
     * Client segment: `mkg` normally, `mkgoefenclient` for a training environment.
     */
    private function clientPath(): string
    {
        return trim($this->stringConfig('mkg.client_path') ?? self::DEFAULT_CLIENT_PATH, '/');
    }

    private function stringConfig(string $key): ?string
    {
        $value = $this->config->get($key);

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
