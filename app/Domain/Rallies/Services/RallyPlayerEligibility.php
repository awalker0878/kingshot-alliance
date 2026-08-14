<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Services;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Player;

final readonly class RallyPlayerEligibility
{
    public function __construct(private EventParticipantAuthorization $events) {}

    public function eligible(Event $event, Alliance $alliance, Player $player): bool
    {
        if (! $this->events->eligible($event, $player)
            || (string) $alliance->kingdom_id !== (string) $player->current_kingdom_id) {
            return false;
        }

        return AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $player->id)
            ->where('state', RosterState::Active->value)
            ->exists();
    }
}
