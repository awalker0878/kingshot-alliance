<?php

declare(strict_types=1);

namespace App\Domain\Authorization\ValueObjects;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Models\AllianceMembership;

final readonly class AllianceMutationContext
{
    public function __construct(
        public Alliance $alliance,
        public Player $actor,
        public AllianceMembership $membership,
    ) {}
}
