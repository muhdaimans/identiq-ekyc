<?php

namespace Muhdaimans\IdentiqEkyc\Data;

use Carbon\CarbonImmutable;

final readonly class IssuedLink
{
    /**
     * @param  string  $idempotencyKey  The key the request was sent with; reuse it to retry safely.
     * @param  bool  $replayed  True when the platform replayed the response of an earlier request with the same key.
     */
    public function __construct(
        public ?string $journeyRef,
        public string $linkUrl,
        public ?string $qrPngUrl,
        public ?CarbonImmutable $expiresAt,
        public string $idempotencyKey,
        public bool $replayed = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $idempotencyKey, bool $replayed = false): self
    {
        return new self(
            journeyRef: Values::string($data['journey_ref'] ?? null),
            linkUrl: (string) ($data['link_url'] ?? ''),
            qrPngUrl: Values::string($data['qr_png_url'] ?? null),
            expiresAt: Values::date($data['expires_at'] ?? null),
            idempotencyKey: $idempotencyKey,
            replayed: $replayed,
        );
    }
}
