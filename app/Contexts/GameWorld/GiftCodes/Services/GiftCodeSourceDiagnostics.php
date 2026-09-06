<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final readonly class GiftCodeSourceDiagnostics
{
    public function __construct(
        public string $healthStatus,
        public float $acceptanceRatio,
        public float $quarantineRatio,
        public float $duplicateRatio,
        public int $reconciliationGaps,
    ) {}
}
