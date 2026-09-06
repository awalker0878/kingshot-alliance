<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeSourceReconciliationPolicy
{
    public function lookbackHours(string $provider): int
    {
        return match ($provider) {
            'x', 'discord', 'youtube', 'facebook', 'century_games' => 48,
            'instagram' => 72,
            'reddit' => 24,
            default => 48,
        };
    }
}
