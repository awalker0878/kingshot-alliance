<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeObservationCluster;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourcePerformanceProjection;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeAcquisitionStatistics;
use Carbon\CarbonImmutable;

final readonly class RebuildGiftCodeSourcePerformance
{
    public function __construct(private GiftCodeAcquisitionStatistics $statistics) {}

    /** @return array{examined:int,updated:int,nextCursor:?string} */
    public function handle(int $limit = 100, ?string $afterSourceId = null): array
    {
        $limit = max(1, min(500, $limit));
        $sources = GiftCodeSourceRegistry::query()
            ->when($afterSourceId !== null && $afterSourceId !== '', static fn ($query) => $query->where('id', '>', $afterSourceId))
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();
        $hasMore = $sources->count() > $limit;
        $sources = $sources->take($limit)->values();
        foreach ($sources as $source) {
            $this->rebuild($source);
        }
        $last = $sources->last();

        return [
            'examined' => $sources->count(),
            'updated' => $sources->count(),
            'nextCursor' => $hasMore && $last instanceof GiftCodeSourceRegistry ? (string) $last->id : null,
        ];
    }

    private function rebuild(GiftCodeSourceRegistry $source): void
    {
        $observations = GiftCodeProvenance::query()
            ->with('giftCode')
            ->where('registered_source_id', $source->id)
            ->orderBy('observed_at')
            ->get();
        $observationCount = $observations->count();
        $uniqueCodes = $observations->pluck('gift_code_id')->unique()->count();
        $qualified = $observations->filter(static fn (GiftCodeProvenance $provenance): bool => $provenance->verification_state === GiftCodeEvidenceVerificationState::Verified);
        $rejected = $observations->filter(static fn (GiftCodeProvenance $provenance): bool => $provenance->verification_state === GiftCodeEvidenceVerificationState::Rejected);
        $conflictingCodes = $observations
            ->filter(static fn (GiftCodeProvenance $provenance): bool => $provenance->giftCode?->status === GiftCodeStatus::Disputed)
            ->pluck('gift_code_id')
            ->unique()
            ->count();

        $discoveryLatencies = [];
        foreach ($observations as $observation) {
            if ($observation->published_at === null) {
                continue;
            }
            $seconds = $observation->observed_at->getTimestamp() - $observation->published_at->getTimestamp();
            if ($seconds >= 0) {
                $discoveryLatencies[] = $seconds;
            }
        }

        $confirmationLatencies = [];
        foreach ($observations->groupBy('gift_code_id') as $codeObservations) {
            $earliest = $codeObservations->sortBy(static fn (GiftCodeProvenance $provenance): int => $provenance->observed_at->getTimestamp())->first();
            if (! $earliest instanceof GiftCodeProvenance || $earliest->giftCode?->status_changed_at === null) {
                continue;
            }
            if (! in_array($earliest->giftCode->status, [GiftCodeStatus::Valid, GiftCodeStatus::Expired], true)) {
                continue;
            }
            $seconds = $earliest->giftCode->status_changed_at->getTimestamp() - $earliest->observed_at->getTimestamp();
            if ($seconds >= 0) {
                $confirmationLatencies[] = $seconds;
            }
        }

        $clusters = GiftCodeObservationCluster::query()
            ->where('earliest_source_id', $source->id)
            ->whereNotNull('time_to_code_seconds')
            ->pluck('time_to_code_seconds')
            ->map(static fn ($value): int => (int) $value)
            ->values()
            ->all();
        $firstDiscoveries = GiftCodeObservationCluster::query()->where('earliest_source_id', $source->id)->count();
        $denominator = max(1, $observationCount);
        $projection = GiftCodeSourcePerformanceProjection::query()->firstOrNew(['gift_code_source_id' => (string) $source->id]);
        $projection->forceFill([
            'observations' => $observationCount,
            'unique_codes_discovered' => $uniqueCodes,
            'first_discoveries' => $firstDiscoveries,
            'qualified_observations' => $qualified->count(),
            'confirmed_correct' => $qualified->count(),
            'confirmed_incorrect' => $rejected->count(),
            'conflicting_observations' => $conflictingCodes,
            'median_discovery_latency_seconds' => $this->statistics->median($discoveryLatencies),
            'median_confirmation_latency_seconds' => $this->statistics->median($confirmationLatencies),
            'median_time_to_code_seconds' => $this->statistics->median($clusters),
            'p95_time_to_code_seconds' => count($clusters) >= 5 ? $this->statistics->percentile($clusters, 0.95) : null,
            'useful_observation_ratio' => round($qualified->count() / $denominator, 6),
            'quarantine_ratio' => round(((int) $source->quarantined_observation_count) / max(1, (int) $source->observation_count), 6),
            'duplicate_ratio' => round(((int) $source->duplicate_observation_count) / max(1, (int) $source->observation_count), 6),
            'latency_sample_count' => count($discoveryLatencies),
            'last_productive_observation_at' => $qualified->max('observed_at'),
            'revision' => ((int) $projection->revision) + 1,
            'derived_at' => CarbonImmutable::now('UTC'),
        ])->save();
    }
}
