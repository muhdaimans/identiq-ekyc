<?php

namespace Muhdaimans\IdentiqEkyc\Enums;

enum SandboxOutcome: string
{
    case Pass = 'PASS';
    case Reject = 'REJECT';
    case ManualReview = 'MANUAL_REVIEW';
    case Recapture = 'RECAPTURE';
    case SystemError = 'SYSTEM_ERROR';
}
