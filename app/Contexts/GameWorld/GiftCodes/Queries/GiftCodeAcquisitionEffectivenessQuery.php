<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Queries;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeObservationCluster;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourcePerformanceProjection;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeAcquisitionStatistics;

final readonly class GiftCodeAcquisitionEffectivenessQuery
{
    public function __construct(private GiftCodeAcquisitionStatistics $statistics) {}

    /** @return array<string,mixed> */
    public function get(): array
    {
        $timeToCode = GiftCodeObservationCluster::query()
            ->whereNotNull('time_to_code_seconds')
            ->pluck('time_to_code_seconds')
            ->map(static fn ($value): int => (int) $value)
            ->all();
        $clusters = GiftCodeObservationCluster::query();

        return [
            'codesMeasured' => (clone $clusters)->count(),
            'timeToCodeSamples' => count($timeToCode),
            'medianTimeToCodeSeconds' => $this->statistics->median($timeToCode),
            'p95TimeToCodeSeconds' => count($timeToCode) >= 5 ? $this->statistics->percentile($timeToCode, 0.95) : null,
            'observations' => (int) (clone $clusters)->sum('observation_count'),
            'distinctSources' => (int) (clone $clusters)->sum('distinct_source_count'),
            'independentSources' => (int) (clone $clusters)->sum('independent_source_count'),
            'officialSources' => (int) (clone $clusters)->sum('official_source_count'),
            'sourcePerformanceRows' => GiftCodeSourcePerformanceProjection::query()->count(),
        ];
    }
}
