<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\ValueObjects;

final readonly class IssuedInvitation
{
    public function __construct(
        public string $invitationId,
        public string $token,
    ) {}
}
