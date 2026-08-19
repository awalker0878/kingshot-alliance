<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Queries;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;

final class AllianceReferenceQuery
{
    public function find(string $allianceId): ?AllianceReference
    {
        $alliance = Alliance::query()->find($allianceId);

        return $alliance instanceof Alliance ? $this->snapshot($alliance) : null;
    }

    public function require(string $allianceId): AllianceReference
    {
        return $this->snapshot(Alliance::query()->findOrFail($allianceId));
    }

    public function lockCurrent(string $allianceId): AllianceReference
    {
        return $this->snapshot(Alliance::query()->whereKey($allianceId)->lockForUpdate()->firstOrFail());
    }

    public function exists(string $allianceId): bool
    {
        return Alliance::query()->whereKey($allianceId)->exists();
    }

    /** @return list<AllianceReference> */
    public function all(int $limit = 500): array
    {
        return array_values(
            Alliance::query()    
            ->orderBy('id')    
            ->limit(max(1, min(5000, $limit)))    
            ->get()    
            ->map(fn (Alliance $alliance): AllianceReference => $this->snapshot($alliance))    
            ->values()    
            ->all(),
        );
    }

    /** @return list<AllianceReference> */
    public function inKingdom(string $kingdomId, bool $activeOnly = false): array
    {
        $query = Alliance::query()
            ->where('kingdom_id', $kingdomId)
            ->orderBy('name');

        if ($activeOnly) {
            $query->where('status', 'active');
        }

        return array_values(
            $query->get()    
            ->map(fn (Alliance $alliance): AllianceReference => $this->snapshot($alliance))    
            ->values()    
            ->all(),
        );
    }

    /**
     * @param  list<string>  $allianceIds
     * @return array<string, AllianceReference>
     */
    public function byIds(array $allianceIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (string $id): string => trim($id),
            $allianceIds,
        ), static fn (string $id): bool => $id !== '')));

        if ($ids === []) {
            return [];
        }

        $references = [];
        foreach (Alliance::query()->whereIn('id', $ids)->get() as $alliance) {
            $reference = $this->snapshot($alliance);
            $references[$reference->allianceId] = $reference;
        }

        return $references;
    }

    private function snapshot(Alliance $alliance): AllianceReference
    {
        return new AllianceReference(
            allianceId: (string) $alliance->id,
            name: (string) $alliance->name,
            slug: (string) $alliance->slug,
            kingdomId: (string) $alliance->kingdom_id,
            language: (string) $alliance->language,
            timezone: (string) $alliance->timezone,
            status: $alliance->status->value,
        );
    }
}
