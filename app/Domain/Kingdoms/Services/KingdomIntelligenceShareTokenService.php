<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Services;

final class KingdomIntelligenceShareTokenService
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
