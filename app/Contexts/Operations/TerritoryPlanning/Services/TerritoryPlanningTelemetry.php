<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use Illuminate\Support\Facades\Log;

final class TerritoryPlanningTelemetry
{
    public function analysisCompleted(
        string $mapDatasetId,
        int $objectCount,
        int $allianceCount,
        float $elapsedMilliseconds,
    ): void {
        Log::info('territory.analysis.completed', [
            'map_dataset_id' => $mapDatasetId,
            'object_count' => $objectCount,
            'alliance_count' => $allianceCount,
            'elapsed_ms' => round($elapsedMilliseconds, 2),
        ]);
    }
}
