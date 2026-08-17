<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\ValueObjects;

use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;

final readonly class PlayerReference implements AuditActor
{
    public function __construct(
        public string $playerId,
        public ?int $userId,
        public string $kingdomId,
        public string $currentName,
        public ?string $gamePlayerId,
    ) {}

    public function claimed(): bool
    {
        return $this->userId !== null;
    }

    public function auditUserId(): ?int
    {
        return null;
    }

    public function auditPlayerId(): string
    {
        return $this->playerId;
    }
}
