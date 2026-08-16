<?php

declare(strict_types=1);

namespace App\Workflows\KingdomTransfer\Enums;

enum TransferDirection: string
{
    case Staying = 'staying';
    case Outgoing = 'outgoing';
    case Incoming = 'incoming';

    public function isRosterBound(): bool
    {
        return $this !== self::Incoming;
    }
}
