<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeSourceBackfillPolicy
{
    public function pagesPerRun(string $provider): int
    {
        return match ($provider) {
            'reddit' => 1,
            'youtube', 'facebook', 'instagram' => 2,
            default => 3,
        };
    }
}
