<?php

namespace Muhdaimans\IdentiqEkyc\Enums;

enum RevisionCause: string
{
    case Automated = 'automated';
    case Review = 'review';
    case Correction = 'correction';
}
