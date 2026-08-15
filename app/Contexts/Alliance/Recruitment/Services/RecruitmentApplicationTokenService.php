<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Services;

final class RecruitmentApplicationTokenService
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
