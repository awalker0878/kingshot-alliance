<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferSourceType: string
{
    case OfficialPublication = 'official_publication';
    case InGame = 'in_game';
    case Evidence = 'evidence';
    case ManagerNote = 'manager_note';
    case Community = 'community';

    public function isAuthoritative(): bool
    {
        return in_array($this, [self::OfficialPublication, self::InGame, self::Evidence], true);
    }
}
