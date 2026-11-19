<?php

namespace Muhdaimans\IdentiqEkyc\Enums;

enum WebhookEventType: string
{
    case Decided = 'journey.decided';
    case RecaptureRequested = 'journey.recapture_requested';
    case ReviewStarted = 'journey.review_started';
    case ReviewDecided = 'journey.review_decided';
    case Expired = 'journey.expired';
    case Abandoned = 'journey.abandoned';
    case SystemError = 'journey.system_error';
    case Test = 'journey.test';
}
