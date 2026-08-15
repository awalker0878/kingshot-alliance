<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Queries;

use App\Domain\Alliances\Models\Alliance;
use App\Contexts\GameWorld\Models\KingdomAllianceDiplomacyContact;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use Illuminate\Database\Eloquent\Collection;

final class KingdomAllianceDiplomacyContactQuery
{
    public const CONTACT_LIMIT = 250;

    public function tracking(Alliance $alliance, string $trackingId): TrackedKingdomAlliance
    {
        return TrackedKingdomAlliance::query()
            ->where('alliance_id', $alliance->id)
            ->with([
                'kingdomAlliance:id,kingdom_id,current_name,current_tag,status',
                'kingdom:id,number,status',
            ])
            ->findOrFail($trackingId);
    }

    /** @return Collection<int, KingdomAllianceDiplomacyContact> */
    public function contacts(Alliance $alliance, string $trackingId): Collection
    {
        return KingdomAllianceDiplomacyContact::query()
            ->where('alliance_id', $alliance->id)
            ->where('tracked_kingdom_alliance_id', $trackingId)
            ->with([
                'createdBy:id,current_name',
                'updatedBy:id,current_name',
                'deactivatedBy:id,current_name',
            ])
            ->orderBy('state')
            ->orderBy('display_name')
            ->orderByDesc('updated_at')
            ->limit(self::CONTACT_LIMIT)
            ->get();
    }
}
