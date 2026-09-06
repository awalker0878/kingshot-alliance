<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeSourceFreshnessPolicy
{
    public function headPollIntervalSeconds(string $provider): int
    {
        return match ($provider) {
            'century_games' => 300,
            'x', 'discord', 'youtube', 'facebook' => 900,
            'instagram' => 900,
            'reddit' => 1800,
            default => 900,
        };
    }

    public function reconciliationIntervalSeconds(string $provider): int
    {
        return match ($provider) {
            'century_games', 'x', 'discord', 'youtube', 'facebook' => 3600,
            'instagram' => 3600,
            'reddit' => 7200,
            default => 3600,
        };
    }
}
