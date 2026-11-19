<?php

namespace Muhdaimans\IdentiqEkyc\Data;

final readonly class JourneyPage
{
    /**
     * @param  list<Journey>  $journeys
     */
    public function __construct(
        public array $journeys,
        public ?string $nextCursor,
        public ?string $prevCursor,
        public ?int $perPage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $meta = Values::array($data['meta'] ?? []);

        return new self(
            journeys: array_map(Journey::fromArray(...), array_values(array_filter(Values::array($data['data'] ?? []), 'is_array'))),
            nextCursor: Values::string($meta['next_cursor'] ?? null),
            prevCursor: Values::string($meta['prev_cursor'] ?? null),
            perPage: Values::int($meta['per_page'] ?? null),
        );
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null && $this->nextCursor !== '';
    }
}
