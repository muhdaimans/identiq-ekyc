<?php

namespace Muhdaimans\IdentiqEkyc\Data;

use Muhdaimans\IdentiqEkyc\Enums\CheckResult;

final readonly class Check
{
    /**
     * @param  list<string>  $reasonCodes
     */
    public function __construct(
        public string $step,
        public int $attempt,
        public ?CheckResult $result,
        public ?float $score,
        public ?float $threshold,
        public array $reasonCodes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            step: (string) ($data['step'] ?? ''),
            attempt: (int) ($data['attempt'] ?? 0),
            result: CheckResult::tryFrom((string) ($data['result'] ?? '')),
            score: Values::float($data['score'] ?? null),
            threshold: Values::float($data['threshold'] ?? null),
            reasonCodes: Values::strings($data['reason_codes'] ?? []),
        );
    }
}
