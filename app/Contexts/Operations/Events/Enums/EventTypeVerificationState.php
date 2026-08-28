<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Enums;

enum EventTypeVerificationState: string
{
    case Candidate = 'candidate';
    case Verified = 'verified';
    case Conflicting = 'conflicting';
    case Unsupported = 'unsupported';
}
