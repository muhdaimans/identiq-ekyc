<?php

namespace Muhdaimans\IdentiqEkyc\Data;

/**
 * The normalised result. icNumber is masked (******-**-1234) unless the token
 * holds results:read_pii; webhook payloads are always masked.
 */
final readonly class JourneyResult
{
    /**
     * @param  array<string, float>|null  $scores
     */
    public function __construct(
        public ?string $fullName,
        public ?string $icNumber,
        public ?string $cardType,
        public ?string $gender,
        public ?string $nationality,
        public ?array $scores,
        public ?float $ocrConfidence,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fullName: Values::string($data['full_name'] ?? null),
            icNumber: Values::string($data['ic_number'] ?? null),
            cardType: Values::string($data['card_type'] ?? null),
            gender: Values::string($data['gender'] ?? null),
            nationality: Values::string($data['nationality'] ?? null),
            scores: is_array($data['scores'] ?? null) ? array_map('floatval', $data['scores']) : null,
            ocrConfidence: Values::float($data['ocr_confidence'] ?? null),
        );
    }

    public function isIcMasked(): bool
    {
        return $this->icNumber !== null && str_contains($this->icNumber, '*');
    }
}
