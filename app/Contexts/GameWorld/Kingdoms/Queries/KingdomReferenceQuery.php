<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Queries;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomReference;

final class KingdomReferenceQuery
{
    public function find(string $kingdomId): ?KingdomReference
    {
        $kingdom = Kingdom::query()->find($kingdomId);

        return $kingdom instanceof Kingdom ? $this->snapshot($kingdom) : null;
    }

    public function require(string $kingdomId): KingdomReference
    {
        return $this->snapshot(Kingdom::query()->findOrFail($kingdomId));
    }

    public function lockCurrent(string $kingdomId): KingdomReference
    {
        return $this->snapshot(Kingdom::query()->whereKey($kingdomId)->lockForUpdate()->firstOrFail());
    }

    public function findByNumber(int $number): ?KingdomReference
    {
        $kingdom = Kingdom::query()->where('number', $number)->first();

        return $kingdom instanceof Kingdom ? $this->snapshot($kingdom) : null;
    }

    /** @param list<string> $kingdomIds @return array<string,KingdomReference> */
    public function byIds(array $kingdomIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $kingdomIds))));
        if ($ids === []) {
            return [];
        }
        $references = [];
        foreach (Kingdom::query()->whereIn('id', $ids)->get() as $kingdom) {
            $reference = $this->snapshot($kingdom);
            $references[$reference->kingdomId] = $reference;
        }

        return $references;
    }

    private function snapshot(Kingdom $kingdom): KingdomReference
    {
        return new KingdomReference(
            kingdomId: (string) $kingdom->id,
            number: (int) $kingdom->number,
            status: is_object($kingdom->status) && property_exists($kingdom->status, 'value')
                ? (string) $kingdom->status->value
                : (string) $kingdom->status,
        );
    }
}
