<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Queries;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use Illuminate\Database\Eloquent\Collection;

final class KingdomAllianceObservationQuery
{
    public const FRESH_DAYS = 30;

    public const HISTORY_LIMIT = 250;

    public function tracking(Alliance $alliance, string $trackingId): TrackedKingdomAlliance
    {
        return TrackedKingdomAlliance::query()
            ->where('alliance_id', $alliance->id)
            ->with([
                'kingdomAlliance:id,kingdom_id,game_alliance_id,current_name,current_tag,status',
                'kingdom:id,number,status',
            ])
            ->findOrFail($trackingId);
    }

    public function latestAccepted(Alliance $alliance, string $trackingId): ?KingdomAllianceObservation
    {
        return KingdomAllianceObservation::query()
            ->where('alliance_id', $alliance->id)
            ->where('tracked_kingdom_alliance_id', $trackingId)
            ->whereNull('invalidated_at')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();
    }

    /** @return Collection<int, KingdomAllianceObservation> */
    public function history(Alliance $alliance, string $trackingId, bool $includeInvalidated): Collection
    {
        return KingdomAllianceObservation::query()
            ->where('alliance_id', $alliance->id)
            ->where('tracked_kingdom_alliance_id', $trackingId)
            ->when(! $includeInvalidated, fn ($query) => $query->whereNull('invalidated_at'))
            ->with(['actor:id,current_name', 'invalidatedBy:id,current_name'])
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get();
    }

    public function freshness(?KingdomAllianceObservation $latest): string
    {
        if (! $latest instanceof KingdomAllianceObservation) {
            return 'missing';
        }

        return $latest->captured_at->gte(now()->subDays(self::FRESH_DAYS)) ? 'current' : 'stale';
    }
}
