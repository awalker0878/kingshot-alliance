<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventRosterMemberStatus: string
{
    case Assigned = 'assigned';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Removed = 'removed';
    case Participated = 'participated';
    case Absent = 'absent';

    public function occupiesSlot(): bool
    {
        return ! in_array($this, [self::Declined, self::Removed], true);
    }
}
