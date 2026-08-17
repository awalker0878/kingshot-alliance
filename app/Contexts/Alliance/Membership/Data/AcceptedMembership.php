<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Data;

final readonly class AcceptedMembership
{
    public function __construct(
        public string $membershipId,
        public string $allianceId,
        public string $playerId,
    ) {}
}
