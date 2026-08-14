<?php

declare(strict_types=1);

namespace App\Domain\Authorization\ValueObjects;

use App\Domain\Kingdoms\Models\Player;

final readonly class PlayerMutationContext
{
    public function __construct(public Player $actor) {}
}
