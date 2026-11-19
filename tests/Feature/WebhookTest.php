<?php

namespace Muhdaimans\IdentiqEkyc\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Muhdaimans\IdentiqEkyc\Enums\ClientState;
use Muhdaimans\IdentiqEkyc\Enums\RecaptureGroup;
use Muhdaimans\IdentiqEkyc\Enums\WebhookEventType;
use Muhdaimans\IdentiqEkyc\Events\WebhookReceived;
use Muhdaimans\IdentiqEkyc\Tests\TestCase;
use Muhdaimans\IdentiqEkyc\Webhooks\SignatureVerifier;

class WebhookTest extends TestCase
{
    private function deliver(array $payload, array $headers = [], ?string $secret = 'whsec_new', ?int $timestamp = null)
    {
        $body = json_encode($payload);
        $timestamp ??= time();

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EKYC_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_EKYC_EVENT' => $payload['event'] ?? '',
            'HTTP_X_EKYC_EVENT_ID' => $payload['event_id'] ?? '',
            'HTTP_X_EKYC_DELIVERY_ATTEMPT' => '1',
        ];

        if ($secret !== null) {
            $server['HTTP_X_EKYC_SIGNATURE'] = SignatureVerifier::sign($secret, $timestamp, $body);
        }

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', '/identiq/webhook', [], [], [], $server, $body);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'event_id' => '6f1c1f1e-0000-4000-8000-000000000001',
            'event' => 'journey.recapture_requested',
            'occurred_at' => '2026-10-15T10:00:00Z',
            'journey_ref' => 'J-1',
            'state' => 'RECAPTURE',
            'reason_codes' => ['BLUR'],
            'recapture_group' => 'selfie',
            'result' => ['ic_number' => '******-**-1234'],
        ], $overrides);
    }

    public function test_a_signed_delivery_dispatches_the_event(): void
    {
        Event::fake([WebhookReceived::class]);

        $this->deliver($this->payload())->assertOk()->assertJson(['received' => true]);

        Event::assertDispatched(WebhookReceived::class, function (WebhookReceived $e) {
            return $e->webhook->is(WebhookEventType::RecaptureRequested)
                && $e->webhook->state === ClientState::Recapture
                && $e->webhook->recaptureGroup === RecaptureGroup::Selfie
                && $e->webhook->result->isIcMasked();
        });
    }

    public function test_the_old_secret_is_accepted_during_rotation(): void
    {
        Event::fake([WebhookReceived::class]);

        $this->deliver($this->payload(), secret: 'whsec_old')->assertOk();
        Event::assertDispatchedTimes(WebhookReceived::class, 1);
    }

    public function test_the_previous_signature_header_is_checked(): void
    {
        Event::fake([WebhookReceived::class]);
        $this->app['config']->set('identiq.webhooks.secrets', 'whsec_old');
        $this->app->forgetInstance(SignatureVerifier::class);

        $payload = $this->payload();
        $ts = time();
        $body = json_encode($payload);

        $this->deliver($payload, [
            'X-Ekyc-Signature' => SignatureVerifier::sign('whsec_brand_new', $ts, $body),
            'X-Ekyc-Signature-Previous' => SignatureVerifier::sign('whsec_old', $ts, $body),
        ], secret: null, timestamp: $ts)->assertOk();
    }

    public function test_a_bad_signature_is_rejected(): void
    {
        Event::fake([WebhookReceived::class]);

        $this->deliver($this->payload(), secret: 'wrong')->assertStatus(401);
        Event::assertNotDispatched(WebhookReceived::class);
    }

    public function test_a_stale_timestamp_is_rejected(): void
    {
        Event::fake([WebhookReceived::class]);

        $this->deliver($this->payload(), timestamp: time() - 301)->assertStatus(401);
        Event::assertNotDispatched(WebhookReceived::class);
    }

    public function test_a_duplicate_event_id_is_acknowledged_without_dispatching(): void
    {
        Event::fake([WebhookReceived::class]);

        $this->deliver($this->payload())->assertOk();
        $this->deliver($this->payload())->assertOk()->assertJson(['duplicate' => true]);

        Event::assertDispatchedTimes(WebhookReceived::class, 1);
    }

    public function test_a_failing_listener_does_not_mark_the_event_as_seen(): void
    {
        $this->withoutExceptionHandling();
        $fail = true;
        Event::listen(WebhookReceived::class, function () use (&$fail) {
            if ($fail) {
                throw new \RuntimeException('db down');
            }
        });

        try {
            $this->deliver($this->payload());
            $this->fail('Expected exception');
        } catch (\RuntimeException) {
        }

        $fail = false;
        $this->deliver($this->payload())->assertOk()->assertJsonMissing(['duplicate' => true]);
    }

    public function test_an_unknown_event_is_still_delivered(): void
    {
        Event::fake([WebhookReceived::class]);

        $this->deliver($this->payload(['event' => 'journey.something_new', 'state' => 'NEW_STATE']))->assertOk();

        Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $e) => $e->webhook->type === null && $e->webhook->event === 'journey.something_new');
    }
}
