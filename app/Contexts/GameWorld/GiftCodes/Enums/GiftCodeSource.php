<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeSource: string
{
    case Manual = 'manual';
    case Official = 'official';
    case Community = 'community';
}
