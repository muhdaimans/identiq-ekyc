<?php

namespace Muhdaimans\IdentiqEkyc\Enums;

enum CheckResult: string
{
    case Pass = 'PASS';
    case Flag = 'FLAG';
    case Fail = 'FAIL';
    case Skipped = 'SKIPPED';
}
