<?php

declare(strict_types=1);

namespace App\Domain\Memberships\ValueObjects;

final readonly class IssuedInvitation
{
    public function __construct(
        public string $invitationId,
        public string $token,
    ) {}
}
