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

    /** @return list<KingdomAllianceReference> */
    public function matchingGameAllianceIdInKingdom(string $kingdomId, string $gameAllianceId, int $limit = 2): array
    {
        return array_values(KingdomAlliance::query()
            ->where('kingdom_id', $kingdomId)
            ->where('game_alliance_id', $gameAllianceId)
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (KingdomAlliance $alliance): KingdomAllianceReference => $this->snapshot($alliance))
            ->values()
            ->all());
    }

    /**
     * @param  array<string>  $kingdomAllianceIds
     * @return array<string, KingdomAllianceReference>
     */
    public function byIds(array $kingdomAllianceIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $kingdomAllianceIds))));
        if ($ids === []) {
            return [];
        }
        $references = [];
        foreach (KingdomAlliance::query()->whereIn('id', $ids)->get() as $alliance) {
            $reference = $this->snapshot($alliance);
            $references[$reference->kingdomAllianceId] = $reference;
        }

        return $references;
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
