<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeSourceTransport: string
{
    case Pull = 'pull';
    case Webhook = 'webhook';
    case Stream = 'stream';
    case Gateway = 'gateway';
    case Manual = 'manual';
}
