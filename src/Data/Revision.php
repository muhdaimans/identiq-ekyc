<?php

namespace Muhdaimans\IdentiqEkyc\Data;

use Carbon\CarbonImmutable;
use Muhdaimans\IdentiqEkyc\Enums\ClientState;
use Muhdaimans\IdentiqEkyc\Enums\RevisionCause;

final readonly class Revision
{
    public function __construct(
        public int $revision,
        public ?ClientState $state,
        public ?RevisionCause $cause,
        public ?CarbonImmutable $at,
        public ?string $reason,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            revision: (int) ($data['revision'] ?? 0),
            state: ClientState::tryFrom((string) ($data['state'] ?? '')),
            cause: RevisionCause::tryFrom((string) ($data['cause'] ?? '')),
            at: Values::date($data['at'] ?? null),
            reason: Values::string($data['reason'] ?? null),
        );
    }
}
