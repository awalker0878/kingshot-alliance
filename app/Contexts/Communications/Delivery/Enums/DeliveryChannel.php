<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Enums;

enum DeliveryChannel: string
{
    case InApp = 'in_app';
    case Discord = 'discord';
    case Telegram = 'telegram';

    public function label(): string
    {
        return match ($this) {
            self::InApp => 'In app',
            self::Discord => 'Discord',
            self::Telegram => 'Telegram',
        };
    }

    public function isExternal(): bool
    {
        return $this !== self::InApp;
    }
}
