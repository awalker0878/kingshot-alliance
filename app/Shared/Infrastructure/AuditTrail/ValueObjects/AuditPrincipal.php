<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\AuditTrail\ValueObjects;

use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;

final readonly class AuditPrincipal implements AuditActor
{
    public function __construct(
        private ?int $userId = null,
        private ?string $playerId = null,
    ) {}

    public static function user(int $userId): self
    {
        return new self(userId: $userId);
    }

    public static function player(string $playerId, ?int $userId = null): self
    {
        return new self(userId: $userId, playerId: $playerId);
    }

    public function auditUserId(): ?int
    {
        return $this->userId;
    }

    public function auditPlayerId(): ?string
    {
        return $this->playerId;
    }
}
