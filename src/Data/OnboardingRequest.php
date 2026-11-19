<?php

namespace Prooffice\IdentiqEkyc\Data;

use Prooffice\IdentiqEkyc\Enums\Channel;
use Prooffice\IdentiqEkyc\Enums\Locale;
use Prooffice\IdentiqEkyc\Enums\SandboxOutcome;

final readonly class OnboardingRequest
{
    /**
     * @param  string  $clientReference  Your own identifier for the applicant (max 128), returned in results.
     * @param  int|null  $ttlMinutes  Link lifetime, 1 to 1440; null uses the platform setting.
     * @param  string|null  $returnUrl  https only.
     * @param  string|null  $expectedName  Name the applicant should match (max 150).
     * @param  string|null  $expectedIc  MyKad number the applicant should match; dashes optional.
     * @param  SandboxOutcome|null  $sandboxOutcome  Sandbox institutions only; others get 422 sandbox_only.
     */
    public function __construct(
        public string $clientReference,
        public ?Channel $channel = null,
        public ?int $ttlMinutes = null,
        public ?string $returnUrl = null,
        public ?Locale $locale = null,
        public ?string $expectedName = null,
        public ?string $expectedIc = null,
        public ?SandboxOutcome $sandboxOutcome = null,
    ) {}

    /**
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return array_filter([
            'client_reference' => $this->clientReference,
            'channel' => $this->channel?->value,
            'ttl_minutes' => $this->ttlMinutes,
            'return_url' => $this->returnUrl,
            'locale' => $this->locale?->value,
            'expected_name' => $this->expectedName,
            'expected_ic' => $this->expectedIc,
            'sandbox_outcome' => $this->sandboxOutcome?->value,
        ], fn ($value) => $value !== null);
    }
}
