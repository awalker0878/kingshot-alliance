<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Enums;

enum BroadcastScheduleStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
