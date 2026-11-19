# IDentiQ eKYC for Laravel

Laravel client for the IDentiQ client API: create onboarding journeys, read results, reconcile, and receive signed webhooks.

Requires PHP 8.2+ and Laravel 11, 12 or 13.

## Install

```bash
composer require prooffice/identiq-ekyc
php artisan vendor:publish --tag=identiq-config   # optional
```

```dotenv
IDENTIQ_CLIENT_ID=...
IDENTIQ_CLIENT_SECRET=...
IDENTIQ_SCOPES="onboarding:write results:read"
IDENTIQ_WEBHOOK_SECRETS=whsec_current            # comma separated during a rotation

# Only when the platform requires mutual TLS for your client
IDENTIQ_MTLS_CERT=/path/client.crt
IDENTIQ_MTLS_KEY=/path/client.key
```

Add `results:read_pii` to the scopes to receive unmasked IC numbers, and `results:read_all` to read journeys created by your institution's other clients. A token only carries scopes your client record holds.

## Create a journey

```php
use Prooffice\IdentiqEkyc\Data\OnboardingRequest;
use Prooffice\IdentiqEkyc\Enums\{Channel, Locale};
use Prooffice\IdentiqEkyc\Facades\Identiq;

$link = Identiq::createOnboarding(new OnboardingRequest(
    clientReference: $applicant->id,
    channel: Channel::Sms,
    locale: Locale::Malay,
    ttlMinutes: 60,
    returnUrl: route('kyc.done'),
    expectedName: $applicant->name,
    expectedIc: $applicant->ic_number,
), idempotencyKey: "onboard-{$applicant->id}");

$link->journeyRef;   // J-20261015-0001
$link->linkUrl;      // send to the applicant
$link->replayed;     // true if the platform replayed an earlier identical request
```

Every POST carries an `Idempotency-Key`. If you don't pass one, a UUID is generated and reused for that call's own retries. Pass your own key, derived from your record, so a retried job can never create a second journey.

## Read results

```php
$journey = Identiq::journey('J-20261015-0001');

$journey->state;                 // ClientState::Pass, ::Processing, ::Recapture, ...
$journey->decision->outcome;     // DecisionOutcome|null (null while processing)
$journey->decision->reasonCodes;
$journey->result?->icNumber;     // masked unless the token holds results:read_pii
$journey->checks;                // list<Check>
$journey->revisions;             // every client-visible result, oldest first
```

Reconciliation feed, following the cursor lazily:

```php
Identiq::allJourneys(updatedSince: $lastSync)->each(function ($journey) {
    // ...
});

// or one page at a time
$page = Identiq::journeys(updatedSince: $lastSync, perPage: 100, cursor: $cursor);
$page->nextCursor;
```

Reissue a link (for example after `journey.recapture_requested`) and get the QR code:

```php
$link = Identiq::reissueLink($ref, new LinkRequest(channel: Channel::Sms, ttlMinutes: 30));
$png  = Identiq::qrCode($ref);
```

## Errors

Every API error is an `IdentiqException` with `status`, `errorCode` (the API `error`), `reason`, `errors` and `retryAfter`:

| Status | Exception | Notes |
|---|---|---|
| 401 | `AuthenticationException` | also for a refused token request |
| 403 | `AuthorizationException` | `isInsufficientScope()`; mTLS problems in `reason` |
| 404 | `NotFoundException` | |
| 409 | `ConflictException` | `isJourneyTerminal()` |
| 422 | `ValidationException` | `isSandboxOnly()`, field errors in `errors` |
| 423 | `OnboardingSuspendedException` | |
| 429 | `RateLimitedException` | |
| 5xx | `ServerException` | |
| — | `ConnectionException` | network error or timeout |

The client retries on its own (`identiq.retry.times`, default 2) on 429, 5xx, connection errors, and a 409 meaning the same Idempotency-Key is still running. It honours `Retry-After` and doesn't retry when `Retry-After` exceeds `identiq.retry.max_wait`. On a 401 it fetches a new token and tries once more. It never retries `journey_terminal`.

## Webhooks

The package registers `POST /identiq/webhook` (`identiq.webhooks.path`), named `identiq.webhook`. Register its full URL with the platform. It:

1. checks `X-Ekyc-Signature` / `X-Ekyc-Signature-Previous` against every configured secret, over the raw body, in constant time, and refuses timestamps more than 5 minutes off (401);
2. acknowledges a repeat `event_id` without dispatching it again;
3. dispatches `Prooffice\IdentiqEkyc\Events\WebhookReceived`.

```php
use Prooffice\IdentiqEkyc\Enums\WebhookEventType;
use Prooffice\IdentiqEkyc\Events\WebhookReceived;

Event::listen(function (WebhookReceived $received) {
    $event = $received->webhook;

    match ($event->type) {
        WebhookEventType::Decided, WebhookEventType::ReviewDecided => $this->settle($event->journeyRef, $event->state),
        WebhookEventType::RecaptureRequested => $this->sendNewLink($event->journeyRef, $event->recaptureGroup),
        default => null,
    };
});
```

If a synchronous listener throws, the endpoint answers 500, the event is not marked as seen, and the platform retries. For slow work, make the listener queued (`ShouldQueue`); the event is then marked as seen once it is queued. Webhook results are always masked; call `Identiq::journey()` with a `results:read_pii` token for the full IC.

**Secret rotation:** set `IDENTIQ_WEBHOOK_SECRETS=new,old` during the 24-hour overlap, then remove the old one.

Set `IDENTIQ_WEBHOOKS_ENABLED=false` to register your own route. Use `VerifyWebhookSignature` as its middleware and `WebhookEvent::fromArray()` to parse the payload.

## Testing your app

Use `Http::fake()` as usual. Fake `*/oauth/token` with `['access_token' => 'x', 'expires_in' => 3600]` alongside the API routes. Sign test webhooks with `SignatureVerifier::sign($secret, $timestamp, $body)`.

```bash
composer test
```
