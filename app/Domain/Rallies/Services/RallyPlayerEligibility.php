<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Services;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\EventParticipantAuthorization;

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
