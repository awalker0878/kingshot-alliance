<?php

declare(strict_types=1);

namespace App\Workflows\AccountOnboarding\Data;

final readonly class InvitationAcceptanceResult
{
    public function __construct(
        public string $playerId,
        public string $allianceId,
        public string $membershipId,
    ) {}
}
