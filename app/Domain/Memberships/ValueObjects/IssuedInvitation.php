<?php

declare(strict_types=1);

namespace App\Domain\Memberships\ValueObjects;

use App\Domain\Memberships\Models\Invitation;

final readonly class IssuedInvitation
{
    public function __construct(
        public Invitation $invitation,
        public string $token,
    ) {}
}
