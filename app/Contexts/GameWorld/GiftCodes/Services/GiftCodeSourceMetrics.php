<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;

final class GiftCodeSourceMetrics
{
    public function snapshot(GiftCodeSourceRegistry $source): GiftCodeSourceMetricsSnapshot
    {
        return new GiftCodeSourceMetricsSnapshot(
            observations: (int) $source->observation_count,
            accepted: (int) $source->accepted_observation_count,
            quarantined: (int) $source->quarantined_observation_count,
            duplicates: (int) $source->duplicate_observation_count,
            reconciliationGaps: (int) $source->reconciliation_gap_count,
        );
    }
}
