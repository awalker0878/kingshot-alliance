<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventObjectiveStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
