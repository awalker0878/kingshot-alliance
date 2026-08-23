<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferKingdomClassification: string
{
    case Ordinary = 'ordinary';
    case Leading = 'leading';
    case Unknown = 'unknown';
}
