<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;

final class GiftCodeSourceDiagnosticsBuilder
{
    public function __construct(private GiftCodeSourceUsefulnessMetrics $metrics) {}

    public function build(GiftCodeSourceRegistry $source): GiftCodeSourceDiagnostics
    {
        $ratios = $this->metrics->forSource($source);

        return new GiftCodeSourceDiagnostics(
            healthStatus: (string) $source->health_status,
            acceptanceRatio: $ratios['acceptance_ratio'],
            quarantineRatio: $ratios['quarantine_ratio'],
            duplicateRatio: $ratios['duplicate_ratio'],
            reconciliationGaps: (int) $source->reconciliation_gap_count,
        );
    }
}
