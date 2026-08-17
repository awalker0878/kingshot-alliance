<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\ValueObjects;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionSubscription;

/** Immutable foreign scope plus the ingestion aggregate owned by this capability. */
final readonly class KingdomIngestionMutationContext
{
    public function __construct(
        public AllianceReference $alliance,
        public KingdomIngestionSubscription $subscription,
    ) {}
}
