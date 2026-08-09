<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Enums;

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
