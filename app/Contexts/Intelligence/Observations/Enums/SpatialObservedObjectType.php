<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Enums;

enum SpatialObservedObjectType: string
{
    case Headquarters = 'headquarters';
    case Banner = 'banner';
    case GovernorCity = 'governor_city';
    case BearTrap = 'bear_trap';
}
