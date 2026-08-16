<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Contracts;

final class WebhookEventCatalog
{
    /** @var list<string> */
    private const PUBLIC_EVENTS = [
        'alliance.created',
        'member.joined',
    ];

    public static function isPublic(string $eventType): bool
    {
        return in_array($eventType, self::PUBLIC_EVENTS, true);
    }

    public static function isValidSelector(string $eventType): bool
    {
        return $eventType === '*' || self::isPublic($eventType);
    }

    /** @return list<string> */
    public static function publicEvents(): array
    {
        return self::PUBLIC_EVENTS;
    }
}
