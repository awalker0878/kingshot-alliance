<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferInvitationAllocationState: string
{
    case Requested = 'requested';
    case Reserved = 'reserved';
    case Issued = 'issued';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function consumesPlannedInventory(): bool
    {
        return $this === self::Reserved;
    }
}
