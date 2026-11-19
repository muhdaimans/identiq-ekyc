<?php

namespace Muhdaimans\IdentiqEkyc\Enums;

enum RecaptureGroup: string
{
    /** Retake the card, keep the selfie. */
    case Document = 'document';

    /** Retake the selfie, keep the card. */
    case Selfie = 'selfie';

    /** Retake both. */
    case All = 'all';
}
