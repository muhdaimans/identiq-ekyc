<?php

namespace Muhdaimans\IdentiqEkyc\Webhooks;

use Muhdaimans\IdentiqEkyc\Exceptions\InvalidWebhookSignature;

/**
 * signature = hex( HMAC-SHA256( secret, "{X-Ekyc-Timestamp}.{raw request body}" ) )
 */
class SignatureVerifier
{
    /**
     * @param  list<string>  $secrets  Current secret first; during a rotation, also the old one.
     */
    public function __construct(
        private readonly array $secrets,
        private readonly int $tolerance = 300,
    ) {}

    public static function sign(string $secret, int|string $timestamp, string $payload): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    /**
     * @param  list<string|null>  $signatures  X-Ekyc-Signature and, during a rotation, X-Ekyc-Signature-Previous.
     *
     * @throws InvalidWebhookSignature
     */
    public function verify(string $payload, ?string $timestamp, array $signatures, ?int $now = null): void
    {
        if ($this->secrets === []) {
            throw new InvalidWebhookSignature('No IDentiQ webhook secret is configured (IDENTIQ_WEBHOOK_SECRETS).');
        }

        if ($timestamp === null || ! preg_match('/^\d{10}$/', $timestamp)) {
            throw new InvalidWebhookSignature('Missing or malformed X-Ekyc-Timestamp.');
        }

        if (abs(($now ?? time()) - (int) $timestamp) > $this->tolerance) {
            throw new InvalidWebhookSignature('X-Ekyc-Timestamp is outside the allowed tolerance.');
        }

        $signatures = array_values(array_filter(
            array_map(fn ($s) => is_string($s) ? strtolower(trim($s)) : '', $signatures),
            fn ($s) => preg_match('/^[0-9a-f]{64}$/', $s) === 1,
        ));

        if ($signatures === []) {
            throw new InvalidWebhookSignature('Missing or malformed X-Ekyc-Signature.');
        }

        foreach ($this->secrets as $secret) {
            $expected = self::sign($secret, $timestamp, $payload);

            foreach ($signatures as $signature) {
                if (hash_equals($expected, $signature)) {
                    return;
                }
            }
        }

        throw new InvalidWebhookSignature('The webhook signature does not match.');
    }
}
