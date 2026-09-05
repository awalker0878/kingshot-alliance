<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeSourceProviderCapabilities
{
    /** @return array{push:bool,head_poll:bool,reconciliation:bool,backfill:bool,authority:string} */
    public function for(string $provider): array
    {
        return match ($provider) {
            'century_games' => ['push' => false, 'head_poll' => true, 'reconciliation' => true, 'backfill' => true, 'authority' => 'official'],
            'x' => ['push' => true, 'head_poll' => true, 'reconciliation' => true, 'backfill' => true, 'authority' => 'official'],
            'discord' => ['push' => true, 'head_poll' => true, 'reconciliation' => true, 'backfill' => true, 'authority' => 'official'],
            'youtube' => ['push' => true, 'head_poll' => true, 'reconciliation' => true, 'backfill' => true, 'authority' => 'official'],
            'facebook' => ['push' => true, 'head_poll' => true, 'reconciliation' => true, 'backfill' => true, 'authority' => 'official'],
            'instagram' => ['push' => false, 'head_poll' => true, 'reconciliation' => true, 'backfill' => true, 'authority' => 'official'],
            'reddit' => ['push' => false, 'head_poll' => true, 'reconciliation' => true, 'backfill' => true, 'authority' => 'independent'],
            default => ['push' => false, 'head_poll' => true, 'reconciliation' => true, 'backfill' => false, 'authority' => 'policy'],
        };
    }
}
