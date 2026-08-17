<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Diplomacy\Queries;

use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacy;
use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacyTransition;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use Illuminate\Database\Eloquent\Collection;

final class KingdomAllianceDiplomacyQuery
{
    public const HISTORY_LIMIT = 250;

    public function tracking(string $allianceId, string $trackingId): TrackedKingdomAlliance
    {
        return TrackedKingdomAlliance::query()
            ->where('alliance_id', $allianceId)
            ->findOrFail($trackingId);
    }

    public function relationship(string $allianceId, string $trackingId): ?KingdomAllianceDiplomacy
    {
        return KingdomAllianceDiplomacy::query()
            ->where('alliance_id', $allianceId)
            ->where('tracked_kingdom_alliance_id', $trackingId)
            ->first();
    }

    /** @return Collection<int, KingdomAllianceDiplomacyTransition> */
    public function history(string $allianceId, string $trackingId): Collection
    {
        return KingdomAllianceDiplomacyTransition::query()
            ->where('alliance_id', $allianceId)
            ->where('tracked_kingdom_alliance_id', $trackingId)
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
