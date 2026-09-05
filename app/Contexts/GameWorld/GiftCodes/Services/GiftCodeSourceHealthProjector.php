<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSourceHealthStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;

final class GiftCodeSourceHealthProjector
{
    public function recordCompletedRun(
        GiftCodeSourceRegistry $source,
        int $examined,
        int $accepted,
        int $duplicates,
        int $quarantined,
    ): void {
        $source->forceFill([
            'health_status' => $quarantined > 0
                ? GiftCodeSourceHealthStatus::Degraded->value
                : ($examined > 0 ? GiftCodeSourceHealthStatus::Healthy->value : GiftCodeSourceHealthStatus::Idle->value),
            'consecutive_failures' => 0,
            'consecutive_quarantined_runs' => $quarantined > 0 ? $source->consecutive_quarantined_runs + 1 : 0,
            'accepted_observation_count' => $source->accepted_observation_count + $accepted,
            'quarantined_observation_count' => $source->quarantined_observation_count + $quarantined,
            'duplicate_observation_count' => $source->duplicate_observation_count + $duplicates,
            'last_accepted_observation_at' => $accepted > 0 ? now() : $source->last_accepted_observation_at,
            'last_quarantined_observation_at' => $quarantined > 0 ? now() : $source->last_quarantined_observation_at,
            'last_ingestion_success_at' => now(),
            'last_ingestion_failure_code' => $quarantined > 0 ? 'observation_quarantined' : null,
            'last_ingestion_error' => $quarantined > 0 ? 'One or more observations were quarantined during the latest ingestion run.' : null,
        ])->save();
    }
}
