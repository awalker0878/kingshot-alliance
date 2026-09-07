<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferCapacityReservationState: string
{
    case Planned = 'planned';
    case Reserved = 'reserved';
    case Confirmed = 'confirmed';
    case Released = 'released';
    case Failed = 'failed';

    public function consumesPlannedCapacity(): bool
    {
        return in_array($this, [self::Planned, self::Reserved, self::Confirmed], true);
    }
}
