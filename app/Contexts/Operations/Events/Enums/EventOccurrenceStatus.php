<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Enums;

enum EventOccurrenceStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
