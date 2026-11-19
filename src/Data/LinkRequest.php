<?php

namespace Muhdaimans\IdentiqEkyc\Data;

use Muhdaimans\IdentiqEkyc\Enums\Channel;

final readonly class LinkRequest
{
    public function __construct(
        public ?Channel $channel = null,
        public ?int $ttlMinutes = null,
        public ?string $returnUrl = null,
    ) {}

    /**
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return array_filter([
            'channel' => $this->channel?->value,
            'ttl_minutes' => $this->ttlMinutes,
            'return_url' => $this->returnUrl,
        ], fn ($value) => $value !== null);
    }
}
