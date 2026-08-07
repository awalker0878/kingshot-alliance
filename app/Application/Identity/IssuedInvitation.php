<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Models\Invitation;

final readonly class IssuedInvitation
{
    public function __construct(
        public Invitation $invitation,
        public string $token,
    ) {}
}
