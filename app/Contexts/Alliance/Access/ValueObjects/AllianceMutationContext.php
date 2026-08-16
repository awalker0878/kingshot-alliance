<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\ValueObjects;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Player;

final readonly class AllianceMutationContext
{
    public function __construct(
        public Alliance $alliance,
        public Player $actor,
        public AllianceMembership $membership,
    ) {}
}
