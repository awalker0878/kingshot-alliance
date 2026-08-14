<?php

declare(strict_types=1);

namespace App\Domain\Authorization\ValueObjects;

use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;

final readonly class KingdomMutationContext
{
    public function __construct(
        public Kingdom $kingdom,
        public Player $actor,
    ) {}
}
