<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Queries;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
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
                'observations' => fn ($query) => $query
                    ->whereNull('invalidated_at')
                    ->orderByDesc('captured_at')
                    ->orderByDesc('id')
                    ->limit(1),
                'diplomacy:id,alliance_id,tracked_kingdom_alliance_id,current_state,effective_at,review_at,expires_at',
            ])
            ->orderBy('state')
            ->orderByDesc('created_at')
            ->get();
    }
}
