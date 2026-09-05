<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use Carbon\CarbonInterface;

final class GiftCodeSourceStaleness
{
    public function isStale(?CarbonInterface $lastSuccessfulRetrievalAt, int $thresholdSeconds): bool
    {
        return $lastSuccessfulRetrievalAt === null
            || $lastSuccessfulRetrievalAt->lt(now()->subSeconds(max(60, $thresholdSeconds)));
    }
}
