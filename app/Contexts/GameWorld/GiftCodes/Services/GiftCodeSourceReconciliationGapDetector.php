<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;

final class GiftCodeSourceReconciliationGapDetector
{
    public function recordGap(GiftCodeSourceRegistry $source): void
    {
        $source->forceFill([
            'last_reconciliation_gap_at' => now(),
            'reconciliation_gap_count' => $source->reconciliation_gap_count + 1,
            'health_status' => 'degraded',
        ])->save();
    }
}
