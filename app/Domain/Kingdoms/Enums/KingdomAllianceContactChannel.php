<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Enums;

enum KingdomAllianceContactChannel: string
{
    case InGame = 'in_game';
    case Discord = 'discord';
    case OtherHandle = 'other_handle';
}
