<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeAccountStateStatus: string
{
    case Actionable = 'actionable';
    case Pinned = 'pinned';
    case Snoozed = 'snoozed';
    case Dismissed = 'dismissed';
}
