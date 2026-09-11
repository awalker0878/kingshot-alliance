<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Enums;

enum BroadcastRunStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Empty = 'empty';
    case Cancelled = 'cancelled';
}
