<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\ValueObjects;

final readonly class KingdomAdministratorBootstrap
{
    public function __construct(
        public string $assignmentId,
        public string $kingdomId,
        public int $kingdomNumber,
        public string $playerId,
        public string $roleKey,
    ) {}
}
