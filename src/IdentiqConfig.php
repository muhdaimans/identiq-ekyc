<?php

namespace Muhdaimans\IdentiqEkyc;

use InvalidArgumentException;

final readonly class IdentiqConfig
{
    /**
     * @param  array<string, mixed>  $httpOptions  Extra Guzzle options (mutual TLS certificate and key).
     */
    public function __construct(
        public string $baseUrl,
        public string $tokenUrl,
        public string $clientId,
        public string $clientSecret,
        public string $scopes,
        public int $timeout = 30,
        public int $connectTimeout = 10,
        public int $retryTimes = 2,
        public int $retryMaxWait = 30,
        public array $httpOptions = [],
        public string $tokenCachePrefix = 'identiq:token:',
    ) {}

    /**
     * @param  array<string, mixed>  $config  The shape of config/identiq.php.
     */
    public static function fromArray(array $config): self
    {
        foreach (['client_id', 'client_secret'] as $key) {
            if (! is_string($config[$key] ?? null) || $config[$key] === '') {
                throw new InvalidArgumentException("IDentiQ config [identiq.{$key}] is not set (IDENTIQ_".strtoupper($key).').');
            }
        }

        $scopes = $config['scopes'] ?? '';
        $scopes = is_array($scopes) ? implode(' ', $scopes) : (string) $scopes;

        return new self(
            baseUrl: rtrim((string) ($config['base_url'] ?? 'https://identiq.prooffice.com.my/api'), '/'),
            tokenUrl: (string) ($config['token_url'] ?? 'https://identiq.prooffice.com.my/oauth/token'),
            clientId: $config['client_id'],
            clientSecret: $config['client_secret'],
            scopes: trim(preg_replace('/\s+/', ' ', $scopes) ?? ''),
            timeout: (int) ($config['timeout'] ?? 30),
            connectTimeout: (int) ($config['connect_timeout'] ?? 10),
            retryTimes: max(0, (int) ($config['retry']['times'] ?? 2)),
            retryMaxWait: max(0, (int) ($config['retry']['max_wait'] ?? 30)),
            httpOptions: self::mtlsOptions((array) ($config['mtls'] ?? [])),
            tokenCachePrefix: (string) ($config['token_cache']['prefix'] ?? 'identiq:token:'),
        );
    }

    /**
     * @param  array<string, mixed>  $mtls
     * @return array<string, mixed>
     */
    private static function mtlsOptions(array $mtls): array
    {
        $options = [];

        if (! empty($mtls['cert'])) {
            $options['cert'] = ! empty($mtls['cert_password']) ? [$mtls['cert'], $mtls['cert_password']] : $mtls['cert'];
        }

        if (! empty($mtls['key'])) {
            $options['ssl_key'] = ! empty($mtls['key_password']) ? [$mtls['key'], $mtls['key_password']] : $mtls['key'];
        }

        return $options;
    }

    public function tokenCacheKey(): string
    {
        return $this->tokenCachePrefix.sha1($this->tokenUrl.'|'.$this->clientId.'|'.$this->scopes);
    }
}
