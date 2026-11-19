<?php

namespace Prooffice\IdentiqEkyc\Data;

use Carbon\CarbonImmutable;
use Prooffice\IdentiqEkyc\Enums\DecisionOutcome;

final readonly class Decision
{
    /**
     * @param  list<string>  $reasonCodes
     * @param  list<string>  $minorFlags  Minor flags an automatic PASS went through (GLARE, POSE, ...).
     */
    public function __construct(
        public ?DecisionOutcome $outcome,
        public array $reasonCodes,
        public array $minorFlags,
        public ?CarbonImmutable $decidedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            outcome: isset($data['outcome']) ? DecisionOutcome::tryFrom((string) $data['outcome']) : null,
            reasonCodes: Values::strings($data['reason_codes'] ?? []),
            minorFlags: Values::strings($data['minor_flags'] ?? []),
            decidedAt: Values::date($data['decided_at'] ?? null),
        );
    }
}
