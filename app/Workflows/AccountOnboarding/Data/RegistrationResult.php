<?php

declare(strict_types=1);

namespace App\Workflows\AccountOnboarding\Data;

final readonly class RegistrationResult
{
    public function __construct(
        public int $userId,
        public ?string $playerId = null,
        public ?string $allianceId = null,
        public ?string $membershipId = null,
    ) {}

    public function joinedAlliance(): bool
    {
        return $this->membershipId !== null && $this->playerId !== null;
    }
}
