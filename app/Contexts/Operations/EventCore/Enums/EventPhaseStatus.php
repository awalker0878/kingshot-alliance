<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Enums;

enum EventPhaseStatus: string
{
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
