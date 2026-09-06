<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeProvider: string
{
    case CenturyGames = 'century_games';
    case X = 'x';
    case Discord = 'discord';
    case YouTube = 'youtube';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Reddit = 'reddit';
}
