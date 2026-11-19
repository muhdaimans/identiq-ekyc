<?php

namespace Muhdaimans\IdentiqEkyc\Enums;

enum ClientState: string
{
    case Processing = 'PROCESSING';
    case Recapture = 'RECAPTURE';
    case ManualReview = 'MANUAL_REVIEW';
    case Pass = 'PASS';
    case Reject = 'REJECT';
    case Expired = 'EXPIRED';
    case Abandoned = 'ABANDONED';
    case SystemError = 'SYSTEM_ERROR';

    /**
     * States after which no new capture link can be issued. A decided result can
     * still be corrected later (a new entry in the journey's revisions).
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::Pass, self::Reject, self::Expired, self::Abandoned, self::SystemError => true,
            default => false,
        };
    }
}
