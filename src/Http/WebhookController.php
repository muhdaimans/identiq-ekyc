<?php

namespace Prooffice\IdentiqEkyc\Http;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Prooffice\IdentiqEkyc\Data\WebhookEvent;
use Prooffice\IdentiqEkyc\Events\WebhookReceived;

/**
 * Receives a signed delivery (checked by VerifyWebhookSignature), skips an
 * event_id already handled, and dispatches WebhookReceived. Any exception from
 * a synchronous listener answers 500 so the platform retries the delivery.
 */
class WebhookController
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly CacheFactory $cache,
        private readonly Config $config,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return new JsonResponse(['error' => 'invalid_payload'], 400);
        }

        $payload['event_id'] ??= $request->header('X-Ekyc-Event-Id');
        $event = WebhookEvent::fromArray($payload, (int) ($request->header('X-Ekyc-Delivery-Attempt') ?? 1));

        if ($event->eventId === '') {
            return new JsonResponse(['error' => 'missing_event_id'], 400);
        }

        $dedupe = (array) $this->config->get('identiq.webhooks.dedupe', []);
        $store = ($dedupe['enabled'] ?? true) ? $this->cache->store($dedupe['store'] ?? null) : null;
        $key = 'identiq:webhook:'.$event->eventId;

        if ($store?->has($key)) {
            return new JsonResponse(['received' => true, 'duplicate' => true]);
        }

        $this->events->dispatch(new WebhookReceived($event));

        $store?->put($key, true, (int) ($dedupe['ttl'] ?? 604800));

        return new JsonResponse(['received' => true]);
    }
}
