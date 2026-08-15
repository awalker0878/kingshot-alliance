<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Catalog;

use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventScope;

final class KingPerkEventCapabilityCatalog
{
    public static function eventTypeSlug(): string
    {
        return 'kingdom-of-power';
    }

    public static function scope(): EventScope
    {
        return EventScope::Kingdom;
    }

    public static function capability(): EventCapability
    {
        return EventCapability::KingPerks;
    }

    /** @return array<string, mixed> */
    public static function configuration(): array
    {
        return [
            'appointment_duration_source' => 'king_perks_catalogue',
            'canonical_timezone' => 'UTC',
            'request_categories' => ['construction', 'research', 'training', 'healing', 'combat'],
            'appointment_reminder_minutes' => [1440, 60, 10],
            'skill_reminder_minutes' => [60],
        ];
    }
}
