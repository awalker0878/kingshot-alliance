<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
