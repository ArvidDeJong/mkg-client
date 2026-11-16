<?php

namespace Darvis\MkgClient\Support;

use Darvis\MkgClient\Contracts\ConfigProviderInterface;
use Darvis\MkgClient\Contracts\CookieStoreInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class SessionCookieManager
{
    private bool $bootstrapped = false;

    private ?MkgEndpoints $endpoints = null;

    public function __construct(
        private readonly Client $client,
        private readonly ConfigProviderInterface $config,
        private readonly CookieStoreInterface $cookieStore,
        private readonly string $cookieStoragePath,
        private ?string $sessionCookie = null,
    ) {}

    /**
     * @throws GuzzleException
     */
    public function bootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }

        $this->sessionCookie ??= $this->readSessionCookie();

        if (! $this->sessionCookie) {
            $this->login();
        }

        $this->bootstrapped = true;
    }

    /**
     * @throws GuzzleException
     */
    public function ensureAuthenticated(): void
    {
        $this->bootstrap();
    }

    /**
     * @throws RuntimeException
     */
    public function validateConfiguration(): void
    {
        $this->assertRequiredConfig([
            'mkg.customer',
            'mkg.username',
            'mkg.password',
        ]);

        // Resolves mkg.url_auth, or builds it from mkg.host; throws when neither is set.
        $this->endpoints()->auth();
    }

    /**
     * @throws GuzzleException
     */
    public function login(): void
    {
        $this->validateConfiguration();

        $response = $this->client->post($this->endpoints()->auth(), [
            'headers' => [
                'X-CustomerID' => $this->customerHeader(),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'j_username' => $this->config->get('mkg.username'),
                'j_password' => $this->config->get('mkg.password'),
            ],
        ]);

        $cookie = explode(';', $response->getHeaderLine('Set-Cookie'))[0];
        $this->sessionCookie = $cookie !== '' ? $cookie : null;

        if ($this->sessionCookie) {
            $this->cookieStore->write($this->cookieStoragePath, $this->sessionCookie);
        }
    }

    /**
     * Guzzle 8 only accepts string header values; a config provider may hand over an int.
     */
    private function customerHeader(): string
    {
        $customer = $this->config->get('mkg.customer');

        return is_scalar($customer) ? (string) $customer : '';
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GuzzleException
     */
    public function buildHeaders(bool $jsonRequest = false): array
    {
        $this->ensureAuthenticated();

        $headers = [
            'X-CustomerID' => $this->customerHeader(),
            // Guzzle 8 refuses null; Guzzle 7 sent it as an empty header, so keep that.
            'Cookie' => (string) $this->sessionCookie,
            'Accept' => 'application/json',
        ];

        if ($jsonRequest) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }

    /**
     * @throws GuzzleException
     */
    public function refresh(): void
    {
        $this->invalidateSessionCookie();
        $this->bootstrapped = false;
        $this->login();
        $this->bootstrapped = true;
    }

    private function endpoints(): MkgEndpoints
    {
        return $this->endpoints ??= new MkgEndpoints($this->config);
    }

    public function getSessionCookie(): ?string
    {
        return $this->sessionCookie;
    }

    /**
     * @param  string[]  $keys
     */
    private function assertRequiredConfig(array $keys): void
    {
        $missing = [];

        foreach ($keys as $key) {
            $value = $this->config->get($key);

            if (! is_scalar($value)) {
                $missing[] = $key;

                continue;
            }

            if (trim((string) $value) === '') {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('Missing MKG configuration values: '.implode(', ', $missing));
        }
    }

    private function readSessionCookie(): ?string
    {
        $cookie = $this->cookieStore->read($this->cookieStoragePath);

        if ($cookie === null) {
            return null;
        }

        if ($cookie === '' || ! str_starts_with($cookie, 'JSESSIONID=')) {
            return null;
        }

        return $cookie;
    }

    private function invalidateSessionCookie(): void
    {
        $this->sessionCookie = null;
        $this->cookieStore->delete($this->cookieStoragePath);
    }
}
