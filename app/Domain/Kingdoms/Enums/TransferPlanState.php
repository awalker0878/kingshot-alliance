<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Enums;

enum TransferPlanState: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Locked = 'locked';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled], true);
    }
}
