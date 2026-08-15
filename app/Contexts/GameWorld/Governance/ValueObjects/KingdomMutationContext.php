<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\ValueObjects;

use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;

final readonly class KingdomMutationContext
{
    public function __construct(
        public Kingdom $kingdom,
        public Player $actor,
    ) {}
}
