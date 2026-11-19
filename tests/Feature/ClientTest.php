<?php

namespace Prooffice\IdentiqEkyc\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Prooffice\IdentiqEkyc\Data\OnboardingRequest;
use Prooffice\IdentiqEkyc\Enums\Channel;
use Prooffice\IdentiqEkyc\Enums\ClientState;
use Prooffice\IdentiqEkyc\Enums\DecisionOutcome;
use Prooffice\IdentiqEkyc\Enums\Locale;
use Prooffice\IdentiqEkyc\Exceptions\AuthenticationException;
use Prooffice\IdentiqEkyc\Exceptions\AuthorizationException;
use Prooffice\IdentiqEkyc\Exceptions\ConflictException;
use Prooffice\IdentiqEkyc\Exceptions\NotFoundException;
use Prooffice\IdentiqEkyc\Exceptions\OnboardingSuspendedException;
use Prooffice\IdentiqEkyc\Exceptions\RateLimitedException;
use Prooffice\IdentiqEkyc\Exceptions\ValidationException;
use Prooffice\IdentiqEkyc\Facades\Identiq;
use Prooffice\IdentiqEkyc\Tests\TestCase;

class ClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
    }

    private function token(int $expiresIn = 3600): array
    {
        return ['access_token' => 'tok-1', 'token_type' => 'Bearer', 'expires_in' => $expiresIn];
    }

    private function link(): array
    {
        return [
            'journey_ref' => 'J-20261015-0001',
            'link_url' => 'https://identiq.test/c/abc',
            'qr_png_url' => 'https://identiq.test/api/v1/journeys/J-20261015-0001/qr.png',
            'expires_at' => '2026-10-15T10:00:00Z',
        ];
    }

    public function test_it_creates_an_onboarding_with_token_and_idempotency_key(): void
    {
        Http::fake([
            'identiq.test/oauth/token' => Http::response($this->token()),
            'identiq.test/api/v1/onboarding' => Http::response($this->link(), 201),
        ]);

        $link = Identiq::createOnboarding(new OnboardingRequest(
            clientReference: 'APP-42',
            channel: Channel::Web,
            locale: Locale::Malay,
            expectedIc: '900101-14-1234',
        ), 'onboard-APP-42');

        $this->assertSame('J-20261015-0001', $link->journeyRef);
        $this->assertSame('onboard-APP-42', $link->idempotencyKey);
        $this->assertFalse($link->replayed);
        $this->assertEquals(CarbonImmutable::parse('2026-10-15T10:00:00Z'), $link->expiresAt);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://identiq.test/oauth/token'
            && $r['grant_type'] === 'client_credentials'
            && $r['client_id'] === 'client-1'
            && $r['scope'] === 'onboarding:write results:read');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://identiq.test/api/v1/onboarding'
            && $r->hasHeader('Authorization', 'Bearer tok-1')
            && $r->hasHeader('Idempotency-Key', 'onboard-APP-42')
            && $r->data() === ['client_reference' => 'APP-42', 'channel' => 'web', 'locale' => 'ms', 'expected_ic' => '900101-14-1234']);
    }

    public function test_it_generates_an_idempotency_key_and_reports_replays(): void
    {
        Http::fake([
            'identiq.test/oauth/token' => Http::response($this->token()),
            'identiq.test/api/v1/onboarding' => Http::response($this->link(), 201, ['Idempotent-Replayed' => 'true']),
        ]);

        $link = Identiq::createOnboarding(['client_reference' => 'APP-1']);

        $this->assertNotEmpty($link->idempotencyKey);
        $this->assertTrue($link->replayed);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1/onboarding') && $r->hasHeader('Idempotency-Key', $link->idempotencyKey));
    }

    public function test_the_token_is_cached_between_calls(): void
    {
        Http::fake([
            'identiq.test/oauth/token' => Http::response($this->token()),
            'identiq.test/api/v1/journeys/*' => Http::response(['journey_ref' => 'J-1', 'state' => 'PROCESSING', 'decision' => ['outcome' => null]]),
        ]);

        Identiq::journey('J-1');
        Identiq::journey('J-1');

        Http::assertSentCount(3);
    }

    public function test_a_401_refreshes_the_token_once(): void
    {
        Http::fake([
            'identiq.test/oauth/token' => Http::sequence()->push(['access_token' => 'old', 'expires_in' => 3600])->push(['access_token' => 'new', 'expires_in' => 3600]),
            'identiq.test/api/v1/journeys/*' => Http::sequence()
                ->push(['error' => 'unauthenticated'], 401)
                ->push(['journey_ref' => 'J-1', 'state' => 'PASS']),
        ]);

        $this->assertSame(ClientState::Pass, Identiq::journey('J-1')->state);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v1/journeys/') && $r->hasHeader('Authorization', 'Bearer new'));
    }

    public function test_a_persistent_401_throws(): void
    {
        Http::fake([
            'identiq.test/oauth/token' => Http::response($this->token()),
            'identiq.test/api/*' => Http::response(['error' => 'unauthenticated'], 401),
        ]);

        $this->expectException(AuthenticationException::class);
        Identiq::journey('J-1');
    }

    public function test_a_refused_token_request_is_an_authentication_error(): void
    {
        Http::fake(['identiq.test/oauth/token' => Http::response(['error' => 'invalid_client', 'error_description' => 'Client authentication failed'], 401)]);

        try {
            Identiq::journey('J-1');
            $this->fail('Expected exception');
        } catch (AuthenticationException $e) {
            $this->assertSame('invalid_client', $e->errorCode);
            $this->assertSame('Client authentication failed', $e->getMessage());
        }
    }

    public function test_it_retries_rate_limits_and_in_progress_with_the_same_key(): void
    {
        Http::fake([
            'identiq.test/oauth/token' => Http::response($this->token()),
            'identiq.test/api/v1/onboarding' => Http::sequence()
                ->push(['error' => 'too_many_requests'], 429, ['Retry-After' => '2'])
                ->push(['error' => 'idempotency_in_progress'], 409, ['Retry-After' => '1'])
                ->push($this->link(), 201),
        ]);

        $link = Identiq::createOnboarding(['client_reference' => 'APP-1'], 'key-1');

        $this->assertSame('J-20261015-0001', $link->journeyRef);
        Sleep::assertSequence([Sleep::for(2)->seconds(), Sleep::for(1)->seconds()]);
        $keys = Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/v1/onboarding'))->map(fn ($p) => $p[0]->header('Idempotency-Key')[0])->unique();
        $this->assertSame(['key-1'], $keys->values()->all());
    }

    public function test_it_gives_up_after_the_configured_retries(): void
    {
        Http::fake([
            'identiq.test/oauth/token' => Http::response($this->token()),
            'identiq.test/api/*' => Http::response(['error' => 'too_many_requests'], 429, ['Retry-After' => '1']),
        ]);

        try {
            Identiq::journey('J-1');
            $this->fail('Expected exception');
        } catch (RateLimitedException $e) {
            $this->assertSame(1, $e->retryAfter);
        }

        Http::assertSentCount(1 + 3);
    }

    public function test_a_retry_after_longer_than_max_wait_is_not_retried(): void
    {
        Http::fake([
            'identiq.test/oauth/token' => Http::response($this->token()),
            'identiq.test/api/*' => Http::response(['error' => 'too_many_requests'], 429, ['Retry-After' => '600']),
        ]);

        $this->expectException(RateLimitedException::class);

        try {
            Identiq::journey('J-1');
        } finally {
            Http::assertSentCount(2);
        }
    }

    public static function errorCases(): array
    {
        return [
            'forbidden' => [403, ['error' => 'insufficient_scope'], AuthorizationException::class],
            'not found' => [404, ['error' => 'not_found'], NotFoundException::class],
            'terminal' => [409, ['error' => 'journey_terminal'], ConflictException::class],
            'sandbox only' => [422, ['error' => 'sandbox_only', 'errors' => ['sandbox_outcome' => ['Sandbox only.']]], ValidationException::class],
            'suspended' => [423, ['error' => 'onboarding_suspended'], OnboardingSuspendedException::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('errorCases')]
    public function test_errors_are_mapped(int $status, array $body, string $class): void
    {
        Http::fake([
            'identiq.test/oauth/token' => Http::response($this->token()),
            'identiq.test/api/*' => Http::response($body, $status),
        ]);

        try {
            Identiq::reissueLink('J-1');
            $this->fail("Expected {$class}");
        } catch (\Prooffice\IdentiqEkyc\Exceptions\IdentiqException $e) {
            $this->assertInstanceOf($class, $e);
            $this->assertSame($status, $e->status);
            $this->assertSame($body['error'], $e->errorCode);
            $this->assertSame($body['errors'] ?? [], $e->errors);
        }
    }

    public function test_journey_terminal_is_not_retried(): void
    {
        Http::fake([
            'identiq.test/oauth/token' => Http::response($this->token()),
            'identiq.test/api/*' => Http::response(['error' => 'journey_terminal'], 409),
        ]);

        try {
            Identiq::reissueLink('J-1', ['ttl_minutes' => 30]);
            $this->fail('Expected exception');
        } catch (ConflictException $e) {
            $this->assertTrue($e->isJourneyTerminal());
        }

        Http::assertSentCount(2);
    }

    public function test_it_maps_a_journey(): void
    {
        Http::fake([
            'identiq.test/oauth/token' => Http::response($this->token()),
            'identiq.test/api/v1/journeys/J-1' => Http::response([
                'journey_ref' => 'J-1',
                'client_reference' => 'APP-1',
                'state' => 'PASS',
                'policy_version' => '2026.1',
                'revision' => 1,
                'revisions' => [['revision' => 1, 'state' => 'PASS', 'cause' => 'automated', 'at' => '2026-10-15T10:00:00Z', 'reason' => null]],
                'decision' => ['outcome' => 'PASS', 'reason_codes' => [], 'minor_flags' => ['GLARE'], 'decided_at' => '2026-10-15T10:00:00Z'],
                'result' => ['full_name' => 'ALI BIN ABU', 'ic_number' => '******-**-1234', 'scores' => ['face_match' => 0.97], 'ocr_confidence' => 0.91],
                'checks' => [['step' => 'face_match', 'attempt' => 1, 'result' => 'PASS', 'score' => 0.97, 'threshold' => 0.8, 'reason_codes' => []]],
                'created_at' => '2026-10-15T09:50:00Z',
                'updated_at' => '2026-10-15T10:00:00Z',
            ]),
        ]);

        $journey = Identiq::journey('J-1');

        $this->assertTrue($journey->isPassed());
        $this->assertTrue($journey->isFinal());
        $this->assertSame(DecisionOutcome::Pass, $journey->decision->outcome);
        $this->assertSame(['GLARE'], $journey->decision->minorFlags);
        $this->assertTrue($journey->result->isIcMasked());
        $this->assertSame(0.97, $journey->result->scores['face_match']);
        $this->assertSame('face_match', $journey->checks[0]->step);
        $this->assertSame(1, $journey->revisions[0]->revision);
    }

    public function test_all_journeys_follows_the_cursor(): void
    {
        Http::fake([
            'identiq.test/oauth/token' => Http::response($this->token()),
            'identiq.test/api/v1/journeys?*' => Http::sequence()
                ->push(['data' => [['journey_ref' => 'J-1'], ['journey_ref' => 'J-2']], 'meta' => ['next_cursor' => 'c2', 'per_page' => 2]])
                ->push(['data' => [['journey_ref' => 'J-3']], 'meta' => ['next_cursor' => null, 'per_page' => 2]]),
        ]);

        $refs = Identiq::allJourneys(CarbonImmutable::parse('2026-10-01T00:00:00Z'), 2)->map->journeyRef->all();

        $this->assertSame(['J-1', 'J-2', 'J-3'], $refs);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'cursor=c2') && str_contains($r->url(), 'updated_since=2026-10-01T00%3A00%3A00%2B00%3A00'));
    }

    public function test_qr_code_returns_png_bytes(): void
    {
        Http::fake([
            'identiq.test/oauth/token' => Http::response($this->token()),
            'identiq.test/api/v1/journeys/J-1/qr.png' => Http::response("\x89PNG...", 200, ['Content-Type' => 'image/png']),
        ]);

        $this->assertStringStartsWith("\x89PNG", Identiq::qrCode('J-1'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'qr.png') && $r->hasHeader('Accept', 'image/png'));
    }
}
