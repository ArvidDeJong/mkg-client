<?php

namespace Darvis\MkgClient\Exceptions;

use Darvis\MkgClient\Support\MkgEndpoints;
use GuzzleHttp\Exception\ClientException;

/**
 * A client error from MKG, with the cause spelled out.
 *
 * Extends Guzzle's ClientException on purpose: existing `catch (ClientException)`
 * blocks keep working, they just get a message that says what to fix.
 *
 * MKG answers two very different problems with similar-looking failures:
 *
 * - **403 with an HTML body** comes from Tomcat, not from MKG, and means the
 *   base URL path is wrong or gone. It is not a permission or session problem,
 *   and retrying or re-authenticating will never fix it.
 * - **401 with a JSON body** (`{"status_code":401,"status_txt":"Not authenticated"}`)
 *   means the session cookie expired. The client re-authenticates and retries.
 */
class MkgHttpException extends ClientException
{
    public static function from(ClientException $previous, string $expectedRestBase): self
    {
        $response = $previous->getResponse();
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        return new self(
            self::explain($status, $body, $expectedRestBase, $previous->getMessage()),
            $previous->getRequest(),
            $response,
            $previous,
        );
    }

    private static function explain(int $status, string $body, string $expectedRestBase, string $original): string
    {
        if ($status === 403 && self::looksLikeHtml($body)) {
            return sprintf(
                'MKG returned 403 with an HTML error page, which means the request never reached the REST API: '
                .'the base URL path is wrong or no longer exists. Configured base: %s. Expected the path to end in "%s" '
                .'(note: "web/v3", not "rest/v3" or "rest/v1"). Set mkg.host and let the client build the URL. '
                .'Original error: %s',
                $expectedRestBase,
                MkgEndpoints::REST_SUFFIX,
                $original,
            );
        }

        $detail = self::jsonDetail($body);

        if ($detail !== null) {
            return sprintf('MKG returned %d: %s. Original error: %s', $status, $detail, $original);
        }

        return $original;
    }

    private static function looksLikeHtml(string $body): bool
    {
        return stripos(ltrim($body), '<!doctype html') === 0 || stripos(ltrim($body), '<html') === 0;
    }

    /**
     * MKG reports API-level problems as JSON; surface that instead of the raw body.
     */
    private static function jsonDetail(string $body): ?string
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return null;
        }

        $text = $decoded['status_txt'] ?? $decoded['message'] ?? null;

        return is_string($text) && trim($text) !== '' ? trim($text) : null;
    }
}
