<?php

namespace Muhdaimans\IdentiqEkyc\Data;

use Carbon\CarbonImmutable;
use Muhdaimans\IdentiqEkyc\Enums\ClientState;

final readonly class Journey
{
    /**
     * @param  list<Revision>  $revisions  Every client-visible result so far, oldest first.
     * @param  list<Check>  $checks  Only on the single-journey read.
     * @param  array<string, mixed>  $raw  The response as received.
     */
    public function __construct(
        public string $journeyRef,
        public ?string $clientReference,
        public ?ClientState $state,
        public ?string $policyVersion,
        public ?int $revision,
        public array $revisions,
        public Decision $decision,
        public ?JourneyResult $result,
        public array $checks,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            journeyRef: (string) ($data['journey_ref'] ?? ''),
            clientReference: Values::string($data['client_reference'] ?? null),
            state: ClientState::tryFrom((string) ($data['state'] ?? '')),
            policyVersion: Values::string($data['policy_version'] ?? null),
            revision: Values::int($data['revision'] ?? null),
            revisions: array_map(Revision::fromArray(...), array_values(array_filter(Values::array($data['revisions'] ?? []), 'is_array'))),
            decision: Decision::fromArray(Values::array($data['decision'] ?? [])),
            result: is_array($data['result'] ?? null) ? JourneyResult::fromArray($data['result']) : null,
            checks: array_map(Check::fromArray(...), array_values(array_filter(Values::array($data['checks'] ?? []), 'is_array'))),
            createdAt: Values::date($data['created_at'] ?? null),
            updatedAt: Values::date($data['updated_at'] ?? null),
            raw: $data,
        );
    }

    public function isProcessing(): bool
    {
        return $this->state === ClientState::Processing;
    }

    public function isPassed(): bool
    {
        return $this->state === ClientState::Pass;
    }

    public function isFinal(): bool
    {
        return $this->state?->isFinal() ?? false;
    }
}
