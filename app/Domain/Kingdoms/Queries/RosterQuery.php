<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Queries;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use Illuminate\Database\Eloquent\Collection;

final class RosterQuery
{
    /** @return Collection<int, AllianceRosterEntry> */
    public function forAlliance(Alliance $alliance): Collection
    {
        return AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->with([
                'player:id,kingdom_id,game_player_id,current_name',
                'membership.user:id,name,email',
            ])
            ->orderByRaw("case state when 'active' then 0 when 'tracked' then 1 else 2 end")
            ->orderBy('observed_name')
            ->get();
    }
}
