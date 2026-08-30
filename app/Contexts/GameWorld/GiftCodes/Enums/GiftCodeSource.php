<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeSource: string
{
    /** Account/Governor supplied observation without an external publication. */
    case Manual = 'manual';

    /** Account/Governor supplied observation attributed to a community source. */
    case Community = 'community';

    /** Observation produced through a platform-approved registered source. */
    case Registered = 'registered';
}
