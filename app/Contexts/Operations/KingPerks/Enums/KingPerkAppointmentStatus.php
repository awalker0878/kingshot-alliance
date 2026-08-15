<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Enums;

enum KingPerkAppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function blocksSchedule(): bool
    {
        return ! in_array($this, [self::Cancelled, self::NoShow], true);
    }
}
