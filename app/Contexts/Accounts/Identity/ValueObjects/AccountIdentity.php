<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\ValueObjects;

use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;

final readonly class AccountIdentity implements AuditActor
{
    public function __construct(
        public int $userId,
        public string $name,
        public string $email,
        public bool $emailVerified,
        public bool $multiFactorConfirmed,
    ) {}

    public function auditUserId(): int
    {
        return $this->userId;
    }

    public function auditPlayerId(): ?string
    {
        return null;
    }
}
