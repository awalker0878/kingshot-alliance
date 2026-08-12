<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Enums;

enum KingdomIngestionBatchState: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Partial = 'partial';
    case Failed = 'failed';
    case Blocked = 'blocked';
}
