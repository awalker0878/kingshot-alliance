<?php

declare(strict_types=1);

namespace App\Domain\Events\Queries;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\Event;
use Illuminate\Support\Collection;

final class EventEligiblePlayerQuery
{
    /** @return Collection<int, Player> */
    public function for(Event $event): Collection
    {
        return match ($event->scope) {
            EventScope::Player => Player::query()->whereKey($event->player_id)->get(),
            EventScope::Kingdom => Player::query()
                ->where('current_kingdom_id', $event->kingdom_id)
                ->orderBy('current_name')
                ->get(),
            EventScope::Alliance => $this->alliancePlayers($event),
        };
    }

    /** @return Collection<int, Player> */
    private function alliancePlayers(Event $event): Collection
    {
        $alliance = Alliance::query()->whereKey($event->alliance_id)->first();
        if (! $alliance instanceof Alliance) {
            return collect();
        }

        $playerIds = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('state', RosterState::Active->value)
            ->whereHas('player', static fn ($query) => $query->where('current_kingdom_id', $alliance->kingdom_id))
            ->pluck('player_id');

        return Player::query()->whereIn('id', $playerIds)->orderBy('current_name')->get();
    }
}
