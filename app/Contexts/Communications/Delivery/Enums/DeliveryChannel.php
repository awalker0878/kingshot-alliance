<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Enums;

enum DeliveryChannel: string
{
    case InApp = 'in_app';
    case Discord = 'discord';
    case Telegram = 'telegram';
    case WebPush = 'web_push';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::InApp => 'In app',
            self::Discord => 'Discord',
            self::Telegram => 'Telegram',
            self::WebPush => 'Web Push',
            self::Email => 'Email',
        };
    }

    public function isExternal(): bool
    {
        return $this !== self::InApp;
    }

    public function usesStoredEndpoint(): bool
    {
        return match ($this) {
            self::Discord, self::Telegram, self::WebPush => true,
            self::InApp, self::Email => false,
        };
    }
}
