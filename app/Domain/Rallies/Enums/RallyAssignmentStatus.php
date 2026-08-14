<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Enums;

enum RallyAssignmentStatus: string
{
    case Assigned = 'assigned';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Participated = 'participated';
    case Absent = 'absent';
    case Removed = 'removed';

    public function occupiesAssignment(): bool
    {
        return ! in_array($this, [self::Declined, self::Removed], true);
    }
}
