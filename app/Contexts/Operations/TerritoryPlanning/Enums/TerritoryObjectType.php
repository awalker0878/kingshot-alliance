<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Enums;

enum TerritoryObjectType: string
{
    case Headquarters = 'headquarters';
    case Banner = 'banner';
    case GovernorCity = 'governor_city';
    case BearTrap = 'bear_trap';
}
