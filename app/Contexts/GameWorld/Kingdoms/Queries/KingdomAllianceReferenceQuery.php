<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Queries;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomAllianceReference;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;

final class KingdomAllianceReferenceQuery
{
    private const MAX_CANONICAL_DEPTH = 32;

    public function find(string $kingdomAllianceId): ?KingdomAllianceReference
    {
        $alliance = KingdomAlliance::query()->find($kingdomAllianceId);

        return $alliance instanceof KingdomAlliance ? $this->snapshot($alliance) : null;
    }

    public function require(string $kingdomAllianceId): KingdomAllianceReference
    {
        return $this->snapshot(KingdomAlliance::query()->findOrFail($kingdomAllianceId));
    }

    public function findActive(string $kingdomAllianceId): ?KingdomAllianceReference
    {
        $alliance = KingdomAlliance::query()
            ->whereKey($kingdomAllianceId)
            ->where('status', KingdomAllianceStatus::Active->value)
            ->whereNull('canonical_kingdom_alliance_id')
            ->whereHas('kingdom', fn ($query) => $query->where('status', KingdomStatus::Active->value))
            ->first();

        return $alliance instanceof KingdomAlliance ? $this->snapshot($alliance) : null;
    }

    public function requireActive(string $kingdomAllianceId): KingdomAllianceReference
    {
        $reference = $this->findActive($kingdomAllianceId);
        if (! $reference instanceof KingdomAllianceReference) {
            throw (new ModelNotFoundException)->setModel(KingdomAlliance::class, [$kingdomAllianceId]);
        }

        return $reference;
    }

    public function findCanonical(string $kingdomAllianceId): ?KingdomAllianceReference
    {
        $alliance = KingdomAlliance::query()->find($kingdomAllianceId);
        if (! $alliance instanceof KingdomAlliance) {
            return null;
        }

        return $this->snapshot($this->canonicalModel($alliance));
    }

    public function requireCanonical(string $kingdomAllianceId): KingdomAllianceReference
    {
        return $this->snapshot($this->canonicalModel(KingdomAlliance::query()->findOrFail($kingdomAllianceId)));
    }

    public function requireActiveCanonical(string $kingdomAllianceId): KingdomAllianceReference
    {
        $canonical = $this->requireCanonical($kingdomAllianceId);

        return $this->requireActive($canonical->kingdomAllianceId);
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

    private function canonicalModel(KingdomAlliance $alliance): KingdomAlliance
    {
        $visited = [];
        for ($depth = 0; $depth < self::MAX_CANONICAL_DEPTH; $depth++) {
            $id = (string) $alliance->id;
            if (isset($visited[$id])) {
                throw new LogicException('Circular Kingdom Alliance canonical identity link detected.');
            }
            $visited[$id] = true;

            $canonicalId = $alliance->canonical_kingdom_alliance_id === null
                ? null
                : (string) $alliance->canonical_kingdom_alliance_id;
            if ($canonicalId === null) {
                return $alliance;
            }

            $alliance = KingdomAlliance::query()->findOrFail($canonicalId);
        }

        throw new LogicException('Kingdom Alliance canonical identity chain exceeds the supported depth.');
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
            canonicalKingdomAllianceId: $alliance->canonical_kingdom_alliance_id === null
                ? null
                : (string) $alliance->canonical_kingdom_alliance_id,
        );
    }
}
