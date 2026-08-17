<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\ValueObjects;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;

final readonly class KingdomAllianceReference
{
    public function __construct(
        public string $kingdomAllianceId,
        public string $kingdomId,
        public ?string $gameAllianceId,
        public string $currentName,
        public ?string $currentTag,
        public KingdomAllianceStatus $statusObservedAtRead,
    ) {}
}
