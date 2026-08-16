<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\ValueObjects;

use App\Contexts\GameWorld\Players\Models\Player;

final readonly class PlayerMutationContext
{
    public function __construct(public Player $actor) {}
}
