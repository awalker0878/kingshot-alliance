<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Queries;

use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomAllianceReference;

final class KingdomAllianceReferenceQuery
{
    public function find(string $kingdomAllianceId): ?KingdomAllianceReference
    {
        $alliance = KingdomAlliance::query()->find($kingdomAllianceId);

        return $alliance instanceof KingdomAlliance ? $this->snapshot($alliance) : null;
    }

    public function require(string $kingdomAllianceId): KingdomAllianceReference
    {
        return $this->snapshot(KingdomAlliance::query()->findOrFail($kingdomAllianceId));
    }

    private function snapshot(KingdomAlliance $alliance): KingdomAllianceReference
    {
        return new KingdomAllianceReference(
            kingdomAllianceId: (string) $alliance->id,
            kingdomId: (string) $alliance->kingdom_id,
            gameAllianceId: $alliance->game_alliance_id === null ? null : (string) $alliance->game_alliance_id,
            currentName: (string) $alliance->current_name,
            currentTag: $alliance->current_tag === null ? null : (string) $alliance->current_tag,
            statusObservedAtRead: $alliance->status,
        );
    }
}
