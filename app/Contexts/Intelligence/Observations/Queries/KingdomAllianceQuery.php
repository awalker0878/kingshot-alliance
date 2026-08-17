<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Queries;

use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use Illuminate\Database\Eloquent\Collection;

final class KingdomAllianceQuery
{
    /** @return Collection<int, TrackedKingdomAlliance> */
    public function forAlliance(string $allianceId): Collection
    {
        return TrackedKingdomAlliance::query()
            ->where('alliance_id', $allianceId)
            ->with([
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
