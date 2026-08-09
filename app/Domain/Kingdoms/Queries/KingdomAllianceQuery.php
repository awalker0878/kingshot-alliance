<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Queries;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use Illuminate\Database\Eloquent\Collection;

final class KingdomAllianceQuery
{
    /** @return Collection<int, TrackedKingdomAlliance> */
    public function forAlliance(Alliance $alliance): Collection
    {
        return TrackedKingdomAlliance::query()
            ->where('alliance_id', $alliance->id)
            ->with([
                'kingdomAlliance:id,kingdom_id,game_alliance_id,current_name,current_tag,status',
                'kingdom:id,number,status',
            ])
            ->orderBy('state')
            ->orderByDesc('created_at')
            ->get();
    }
}
