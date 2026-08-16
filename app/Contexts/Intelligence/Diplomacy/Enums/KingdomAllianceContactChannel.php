<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Diplomacy\Enums;

enum KingdomAllianceContactChannel: string
{
    case InGame = 'in_game';
    case Discord = 'discord';
    case OtherHandle = 'other_handle';
}
