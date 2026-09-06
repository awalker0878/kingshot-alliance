<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeObservationCluster;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use Carbon\CarbonImmutable;

final class RebuildGiftCodeObservationClusters
{
    /** @return array{examined:int,updated:int,nextCursor:?string} */
    public function handle(int $limit = 200, ?string $afterGiftCodeId = null): array
    {
        $limit = max(1, min(1000, $limit));
        $codes = GiftCode::query()
            ->with(['provenances.registeredSource'])
            ->when($afterGiftCodeId !== null && $afterGiftCodeId !== '', static fn ($query) => $query->where('id', '>', $afterGiftCodeId))
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();
        $hasMore = $codes->count() > $limit;
        $codes = $codes->take($limit)->values();

        foreach ($codes as $giftCode) {
            $this->rebuild($giftCode);
        }

        $last = $codes->last();

        return [
            'examined' => $codes->count(),
            'updated' => $codes->count(),
            'nextCursor' => $hasMore && $last instanceof GiftCode ? (string) $last->id : null,
        ];
    }

    private function rebuild(GiftCode $giftCode): void
    {
        $observations = $giftCode->provenances
            ->filter(static fn (GiftCodeProvenance $provenance): bool => $provenance->registered_source_id !== null)
            ->sortBy(static fn (GiftCodeProvenance $provenance): int => $provenance->observed_at->getTimestamp())
            ->values();
        $sourceIds = $observations
            ->pluck('registered_source_id')
            ->filter()
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
        $parents = array_fill_keys($sourceIds, '');
        foreach ($sourceIds as $sourceId) {
            $parents[$sourceId] = $sourceId;
        }

        $fingerprintSources = [];
        foreach ($observations as $observation) {
            $fingerprint = trim((string) $observation->content_fingerprint);
            if ($fingerprint === '') {
                continue;
            }
            $fingerprintSources[$fingerprint][(string) $observation->registered_source_id] = true;
        }
        $sharedFingerprintGroups = 0;
        foreach ($fingerprintSources as $sources) {
            $ids = array_keys($sources);
            if (count($ids) < 2) {
                continue;
            }
            $sharedFingerprintGroups++;
            $first = $ids[0];
            foreach (array_slice($ids, 1) as $other) {
                $this->union($parents, $first, $other);
            }
        }

        $components = [];
        foreach ($sourceIds as $sourceId) {
            $components[$this->find($parents, $sourceId)] = true;
        }
        $first = $observations->first();
        $firstSeenAt = $first instanceof GiftCodeProvenance ? $first->observed_at : null;
        $earliestSourceId = $first instanceof GiftCodeProvenance ? $first->registered_source_id : null;
        $qualifiedPublication = $observations
            ->filter(static fn (GiftCodeProvenance $provenance): bool => $provenance->verification_state === GiftCodeEvidenceVerificationState::Verified && $provenance->published_at !== null)
            ->sortBy(static fn (GiftCodeProvenance $provenance): int => $provenance->published_at?->getTimestamp() ?? PHP_INT_MAX)
            ->first();
        $earliestQualifiedAt = $qualifiedPublication instanceof GiftCodeProvenance ? $qualifiedPublication->published_at : null;
        $timeToCode = null;
        $publicationAfterObservation = false;
        if ($firstSeenAt !== null && $earliestQualifiedAt !== null) {
            $seconds = $firstSeenAt->getTimestamp() - $earliestQualifiedAt->getTimestamp();
            if ($seconds >= 0) {
                $timeToCode = $seconds;
            } else {
                $publicationAfterObservation = true;
            }
        }

        $officialSourceCount = $observations
            ->filter(static fn (GiftCodeProvenance $provenance): bool => $provenance->registeredSource?->classification === 'official')
            ->pluck('registered_source_id')
            ->filter()
            ->unique()
            ->count();
        $revokedSourceCount = $observations
            ->filter(static fn (GiftCodeProvenance $provenance): bool => $provenance->registeredSource?->revoked_at !== null)
            ->pluck('registered_source_id')
            ->filter()
            ->unique()
            ->count();
        $nearSimultaneous = false;
        if ($observations->count() > 1) {
            $firstTimestamp = $observations->first()?->observed_at?->getTimestamp();
            $lastTimestamp = $observations->last()?->observed_at?->getTimestamp();
            $nearSimultaneous = $firstTimestamp !== null && $lastTimestamp !== null && ($lastTimestamp - $firstTimestamp) <= 300;
        }
        $confidence = $sharedFingerprintGroups > 0 ? 'high' : ($nearSimultaneous && count($sourceIds) > 1 ? 'medium' : 'low');

        $projection = GiftCodeObservationCluster::query()->firstOrNew(['gift_code_id' => (string) $giftCode->id]);
        $projection->forceFill([
            'earliest_source_id' => $earliestSourceId,
            'observation_count' => $observations->count(),
            'distinct_source_count' => count($sourceIds),
            'independent_source_count' => count($components),
            'official_source_count' => $officialSourceCount,
            'first_seen_at' => $firstSeenAt,
            'earliest_qualified_publication_at' => $earliestQualifiedAt,
            'time_to_code_seconds' => $timeToCode,
            'correlation_confidence' => $confidence,
            'correlation_signals' => [
                'shared_content_fingerprint_groups' => $sharedFingerprintGroups,
                'near_simultaneous_observations' => $nearSimultaneous,
                'revoked_source_count' => $revokedSourceCount,
                'publication_after_observation' => $publicationAfterObservation,
            ],
            'revision' => ((int) $projection->revision) + 1,
            'derived_at' => CarbonImmutable::now('UTC'),
        ])->save();
    }

    /** @param array<string,string> $parents */
    private function find(array &$parents, string $id): string
    {
        if (($parents[$id] ?? $id) === $id) {
            return $id;
        }
        $parents[$id] = $this->find($parents, $parents[$id]);

        return $parents[$id];
    }

    /** @param array<string,string> $parents */
    private function union(array &$parents, string $left, string $right): void
    {
        $leftRoot = $this->find($parents, $left);
        $rightRoot = $this->find($parents, $right);
        if ($leftRoot !== $rightRoot) {
            $parents[$rightRoot] = $leftRoot;
        }
    }
}
