<?php

namespace Prooffice\IdentiqEkyc\Enums;

enum DecisionOutcome: string
{
    case Pass = 'PASS';
    case Reject = 'REJECT';
    case ManualReview = 'MANUAL_REVIEW';
    case Recapture = 'RECAPTURE';
}
