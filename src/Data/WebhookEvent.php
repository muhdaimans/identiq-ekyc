<?php

namespace Prooffice\IdentiqEkyc\Data;

use Carbon\CarbonImmutable;
use Prooffice\IdentiqEkyc\Enums\ClientState;
use Prooffice\IdentiqEkyc\Enums\RecaptureGroup;
use Prooffice\IdentiqEkyc\Enums\WebhookEventType;

final readonly class WebhookEvent
{
    /**
     * @param  string  $event  The event name as sent, e.g. journey.decided.
     * @param  WebhookEventType|null  $type  null for an event name this package does not know yet.
     * @param  list<string>  $reasonCodes
     * @param  list<string>  $minorFlags
     * @param  RecaptureGroup|null  $recaptureGroup  Only on journey.recapture_requested.
     * @param  JourneyResult|null  $result  Always masked; fetch the journey with results:read_pii for the full IC.
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $eventId,
        public string $event,
        public ?WebhookEventType $type,
        public ?CarbonImmutable $occurredAt,
        public ?string $journeyRef,
        public ?ClientState $state,
        public ?CarbonImmutable $decidedAt,
        public array $reasonCodes,
        public array $minorFlags,
        public ?RecaptureGroup $recaptureGroup,
        public ?JourneyResult $result,
        public int $deliveryAttempt,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, int $deliveryAttempt = 1): self
    {
        $event = (string) ($data['event'] ?? '');

        return new self(
            eventId: (string) ($data['event_id'] ?? ''),
            event: $event,
            type: WebhookEventType::tryFrom($event),
            occurredAt: Values::date($data['occurred_at'] ?? null),
            journeyRef: Values::string($data['journey_ref'] ?? null),
            state: ClientState::tryFrom((string) ($data['state'] ?? '')),
            decidedAt: Values::date($data['decided_at'] ?? null),
            reasonCodes: Values::strings($data['reason_codes'] ?? []),
            minorFlags: Values::strings($data['minor_flags'] ?? []),
            recaptureGroup: RecaptureGroup::tryFrom((string) ($data['recapture_group'] ?? '')),
            result: is_array($data['result'] ?? null) ? JourneyResult::fromArray($data['result']) : null,
            deliveryAttempt: $deliveryAttempt,
            raw: $data,
        );
    }

    public function is(WebhookEventType $type): bool
    {
        return $this->type === $type;
    }
}
