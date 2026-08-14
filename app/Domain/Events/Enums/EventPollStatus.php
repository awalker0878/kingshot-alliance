<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventPollStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
