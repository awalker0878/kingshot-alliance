<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Queries;

use App\Domain\Alliances\Models\Alliance;
use App\Contexts\GameWorld\Models\KingdomAllianceDiplomacy;
use App\Contexts\GameWorld\Models\KingdomAllianceDiplomacyTransition;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use Illuminate\Database\Eloquent\Collection;

final class KingdomAllianceDiplomacyQuery
{
    public const HISTORY_LIMIT = 250;

    public function tracking(Alliance $alliance, string $trackingId): TrackedKingdomAlliance
    {
        return TrackedKingdomAlliance::query()
            ->where('alliance_id', $alliance->id)
            ->with([
                'kingdomAlliance:id,kingdom_id,current_name,current_tag,status',
                'kingdom:id,number,status',
                'diplomacy.lastTransitionPlayer:id,current_name',
            ])
            ->findOrFail($trackingId);
    }

    public function relationship(Alliance $alliance, string $trackingId): ?KingdomAllianceDiplomacy
    {
        return KingdomAllianceDiplomacy::query()
            ->where('alliance_id', $alliance->id)
            ->where('tracked_kingdom_alliance_id', $trackingId)
            ->with('lastTransitionPlayer:id,current_name')
            ->first();
    }

    /** @return Collection<int, KingdomAllianceDiplomacyTransition> */
    public function history(Alliance $alliance, string $trackingId): Collection
    {
        return KingdomAllianceDiplomacyTransition::query()
            ->where('alliance_id', $alliance->id)
            ->where('tracked_kingdom_alliance_id', $trackingId)
            ->with('actor:id,current_name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get();
    }

    public function needsReview(?KingdomAllianceDiplomacy $relationship): bool
    {
        if (! $relationship instanceof KingdomAllianceDiplomacy) {
            return false;
        }

        return ($relationship->review_at !== null && $relationship->review_at->lte(now()))
            || ($relationship->expires_at !== null && $relationship->expires_at->lte(now()));
    }
}
