<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Enums;

enum EventRegistrationStatus: string
{
    case Registered = 'registered';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';
}
