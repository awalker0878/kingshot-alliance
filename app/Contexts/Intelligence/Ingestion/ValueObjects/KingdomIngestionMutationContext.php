<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\ValueObjects;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionSubscription;

final readonly class KingdomIngestionMutationContext
{
    public function __construct(
        public Alliance $alliance,
        public KingdomIngestionSubscription $subscription,
    ) {}
}
