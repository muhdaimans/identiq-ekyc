<?php

namespace Muhdaimans\IdentiqEkyc\Facades;

use Illuminate\Support\Facades\Facade;
use Muhdaimans\IdentiqEkyc\IdentiqClient;

/**
 * @method static \Muhdaimans\IdentiqEkyc\Data\IssuedLink createOnboarding(\Muhdaimans\IdentiqEkyc\Data\OnboardingRequest|array $request, ?string $idempotencyKey = null)
 * @method static \Muhdaimans\IdentiqEkyc\Data\IssuedLink reissueLink(string $ref, \Muhdaimans\IdentiqEkyc\Data\LinkRequest|array $request = [], ?string $idempotencyKey = null)
 * @method static \Muhdaimans\IdentiqEkyc\Data\Journey journey(string $ref)
 * @method static \Muhdaimans\IdentiqEkyc\Data\JourneyPage journeys(?\DateTimeInterface $updatedSince = null, int $perPage = 50, ?string $cursor = null)
 * @method static \Illuminate\Support\LazyCollection<int, \Muhdaimans\IdentiqEkyc\Data\Journey> allJourneys(?\DateTimeInterface $updatedSince = null, int $perPage = 100)
 * @method static string qrCode(string $ref)
 *
 * @see IdentiqClient
 */
class Identiq extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IdentiqClient::class;
    }
}
