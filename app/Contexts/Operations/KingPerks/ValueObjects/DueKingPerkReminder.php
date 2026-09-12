<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\ValueObjects;

use App\Contexts\Operations\KingPerks\Enums\KingPerkReminderKind;

/** A bounded discovery candidate, never permission or source-state authority. */
final readonly class DueKingPerkReminder
{
    public function __construct(
        public string $sourceId,
        public string $planId,
        public string $kingdomId,
        public KingPerkReminderKind $kind,
        public ?string $assignedPlayerId,
    ) {}
}
