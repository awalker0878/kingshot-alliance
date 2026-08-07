<?php

declare(strict_types=1);

namespace App\Application\Identity;

final class InvitationTokenService
{
    public function issue(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
