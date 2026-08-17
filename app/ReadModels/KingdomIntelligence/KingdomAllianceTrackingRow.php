<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomIntelligence;

use Illuminate\Support\Carbon;

final readonly class KingdomAllianceTrackingRow
{
    public function __construct(
        public string $id,
        public string $kingdomAllianceId,
        public string $kingdomId,
        public string $state,
        public string $currentName,
        public ?string $currentTag,
        public int $kingdomNumber,
        public ?string $diplomacyState,
        public ?Carbon $diplomacyEffectiveAt,
        public ?Carbon $diplomacyReviewAt,
        public ?Carbon $diplomacyExpiresAt,
    ) {}
}
