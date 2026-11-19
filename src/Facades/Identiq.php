<?php

namespace Prooffice\IdentiqEkyc\Facades;

use Illuminate\Support\Facades\Facade;
use Prooffice\IdentiqEkyc\IdentiqClient;

/**
 * @method static \Prooffice\IdentiqEkyc\Data\IssuedLink createOnboarding(\Prooffice\IdentiqEkyc\Data\OnboardingRequest|array $request, ?string $idempotencyKey = null)
 * @method static \Prooffice\IdentiqEkyc\Data\IssuedLink reissueLink(string $ref, \Prooffice\IdentiqEkyc\Data\LinkRequest|array $request = [], ?string $idempotencyKey = null)
 * @method static \Prooffice\IdentiqEkyc\Data\Journey journey(string $ref)
 * @method static \Prooffice\IdentiqEkyc\Data\JourneyPage journeys(?\DateTimeInterface $updatedSince = null, int $perPage = 50, ?string $cursor = null)
 * @method static \Illuminate\Support\LazyCollection<int, \Prooffice\IdentiqEkyc\Data\Journey> allJourneys(?\DateTimeInterface $updatedSince = null, int $perPage = 100)
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
