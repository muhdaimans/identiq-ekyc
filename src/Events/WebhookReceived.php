<?php

namespace Muhdaimans\IdentiqEkyc\Events;

use Muhdaimans\IdentiqEkyc\Data\WebhookEvent;

/**
 * A verified, not yet seen IDentiQ webhook delivery.
 */
class WebhookReceived
{
    public function __construct(public readonly WebhookEvent $webhook) {}
}
