<?php

namespace Prooffice\IdentiqEkyc\Auth;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Prooffice\IdentiqEkyc\Exceptions\AuthenticationException;
use Prooffice\IdentiqEkyc\Exceptions\ConnectionException;
use Prooffice\IdentiqEkyc\Exceptions\IdentiqException;
use Prooffice\IdentiqEkyc\IdentiqConfig;

/**
 * OAuth 2.0 client credentials. Tokens last 60 minutes; they are cached,
 * encrypted, until a minute before they expire.
 */
class TokenProvider
{
    private const EXPIRY_MARGIN = 60;

    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
        private readonly Encrypter $encrypter,
        private readonly IdentiqConfig $config,
    ) {}

    public function token(): string
    {
        $cached = $this->cache->get($this->config->tokenCacheKey());

        if (is_string($cached)) {
            try {
                return $this->encrypter->decryptString($cached);
            } catch (DecryptException) {
                // APP_KEY rotated; fetch a new token.
            }
        }

        return $this->fetch();
    }

    public function forget(): void
    {
        $this->cache->forget($this->config->tokenCacheKey());
    }

    private function fetch(): string
    {
        try {
            $response = $this->http
                ->withOptions($this->config->httpOptions)
                ->timeout($this->config->timeout)
                ->connectTimeout($this->config->connectTimeout)
                ->acceptJson()
                ->asForm()
                ->post($this->config->tokenUrl, array_filter([
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->config->clientId,
                    'client_secret' => $this->config->clientSecret,
                    'scope' => $this->config->scopes,
                ], fn ($value) => $value !== ''));
        } catch (HttpConnectionException $e) {
            throw new ConnectionException('Could not reach the IDentiQ token endpoint: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            $exception = IdentiqException::fromResponse($response);

            // OAuth servers answer invalid_client with 400/401; surface every token refusal as authentication.
            if ($response->clientError() && ! in_array($response->status(), [403, 429], true)) {
                throw new AuthenticationException(
                    message: (string) ($response->json('error_description') ?? $exception->getMessage()),
                    status: $response->status(),
                    errorCode: $exception->errorCode,
                    reason: $exception->reason,
                    errors: $exception->errors,
                    retryAfter: $exception->retryAfter,
                    response: $response,
                );
            }

            throw $exception;
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new AuthenticationException('The IDentiQ token endpoint did not return an access_token.', $response->status(), response: $response);
        }

        $ttl = (int) ($response->json('expires_in') ?? 3600) - self::EXPIRY_MARGIN;

        if ($ttl > 0) {
            $this->cache->put($this->config->tokenCacheKey(), $this->encrypter->encryptString($token), $ttl);
        }

        return $token;
    }
}
