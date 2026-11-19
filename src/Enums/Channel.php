<?php

namespace Muhdaimans\IdentiqEkyc\Enums;

enum Channel: string
{
    case Web = 'web';
    case Branch = 'branch';
    case Agent = 'agent';
    case Sms = 'sms';
    case Email = 'email';
}
