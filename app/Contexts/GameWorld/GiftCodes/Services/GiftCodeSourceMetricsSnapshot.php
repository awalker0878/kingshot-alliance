<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final readonly class GiftCodeSourceMetricsSnapshot
{
    public function __construct(
        public int $observations,
        public int $accepted,
        public int $quarantined,
        public int $duplicates,
        public int $reconciliationGaps,
    ) {}
}
