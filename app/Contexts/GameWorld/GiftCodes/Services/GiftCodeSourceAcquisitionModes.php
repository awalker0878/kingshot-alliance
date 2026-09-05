<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeSourceAcquisitionModes
{
    public function forProvider(string $provider): GiftCodeSourceAcquisitionMode
    {
        return match ($provider) {
            'x', 'discord', 'youtube', 'facebook' => new GiftCodeSourceAcquisitionMode(true, true, true, true),
            'century_games', 'instagram', 'reddit' => new GiftCodeSourceAcquisitionMode(false, true, true, true),
            default => new GiftCodeSourceAcquisitionMode(false, true, true, false),
        };
    }
}
