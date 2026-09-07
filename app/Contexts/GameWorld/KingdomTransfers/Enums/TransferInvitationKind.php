<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferInvitationKind: string
{
    case Ordinary = 'ordinary';
    case Special = 'special';
}
