<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventRegistrationStatus: string
{
    case Registered = 'registered';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';
}
