<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\ValueObjects;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceIdentitySource;
use Carbon\CarbonImmutable;

final readonly class KingdomAllianceIdentityReference
{
    public function __construct(
        public string $historyId,
        public string $kingdomAllianceId,
        public string $name,
        public ?string $tag,
        public ?string $gameAllianceId,
        public CarbonImmutable $validFrom,
        public ?CarbonImmutable $validTo,
        public KingdomAllianceIdentitySource $sourceType,
        public ?string $sourceReference,
        public ?CarbonImmutable $observedAt,
        public ?int $confidenceBasisPoints,
        public ?string $reason,
    ) {}
}
