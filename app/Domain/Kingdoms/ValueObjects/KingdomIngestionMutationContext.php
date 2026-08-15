<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\ValueObjects;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;

final readonly class KingdomIngestionMutationContext
{
    public function __construct(
        public Alliance $alliance,
        public KingdomIngestionSubscription $subscription,
    ) {}
}
