<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Services;

use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;

final class EventRosterAllianceSnapshotResolver
{
    public function resolve(Event $event, Player $player): ?string
    {
        if ($event->scope === EventScope::Alliance) {
            return $event->alliance_id === null ? null : (string) $event->alliance_id;
        }

        $query = AllianceRosterEntry::query()
            ->where('player_id', $player->id)
            ->where('state', RosterState::Active->value)
            ->whereHas('alliance', static fn ($builder) => $builder
                ->where('kingdom_id', $player->current_kingdom_id)
                ->where('status', AllianceStatus::Active->value));

        if ($event->scope === EventScope::Kingdom) {
            $query->whereHas('alliance', static fn ($builder) => $builder
                ->where('kingdom_id', $event->kingdom_id)
                ->where('status', AllianceStatus::Active->value));
        }

        $ids = $query->pluck('alliance_id')->unique()->values();

        return $ids->count() === 1 ? (string) $ids->first() : null;
    }
}
