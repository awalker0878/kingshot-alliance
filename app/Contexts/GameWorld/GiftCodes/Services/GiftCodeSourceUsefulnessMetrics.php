<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;

final class GiftCodeSourceUsefulnessMetrics
{
    /** @return array{acceptance_ratio:float,quarantine_ratio:float,duplicate_ratio:float} */
    public function forSource(GiftCodeSourceRegistry $source): array
    {
        $observed = max(0, (int) $source->observation_count);
        if ($observed === 0) {
            return [
                'acceptance_ratio' => 0.0,
                'quarantine_ratio' => 0.0,
                'duplicate_ratio' => 0.0,
            ];
        }

        return [
            'acceptance_ratio' => round(((int) $source->accepted_observation_count) / $observed, 4),
            'quarantine_ratio' => round(((int) $source->quarantined_observation_count) / $observed, 4),
            'duplicate_ratio' => round(((int) $source->duplicate_observation_count) / $observed, 4),
        ];
    }
}
