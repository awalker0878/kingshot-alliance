<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Diplomacy\Queries;

use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacyContact;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use Illuminate\Database\Eloquent\Collection;

final class KingdomAllianceDiplomacyContactQuery
{
    public const CONTACT_LIMIT = 250;

    public function tracking(string $allianceId, string $trackingId): TrackedKingdomAlliance
    {
        return TrackedKingdomAlliance::query()
            ->where('alliance_id', $allianceId)
            ->findOrFail($trackingId);
    }

    /** @return Collection<int, KingdomAllianceDiplomacyContact> */
    public function contacts(string $allianceId, string $trackingId): Collection
    {
        return KingdomAllianceDiplomacyContact::query()
            ->where('alliance_id', $allianceId)
            ->where('tracked_kingdom_alliance_id', $trackingId)
            ->orderBy('state')
            ->orderBy('display_name')
            ->orderByDesc('updated_at')
            ->limit(self::CONTACT_LIMIT)
            ->get();
    }
}
