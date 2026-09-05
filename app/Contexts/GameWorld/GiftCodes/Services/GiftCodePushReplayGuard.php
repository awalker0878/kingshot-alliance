<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceDelivery;

final class GiftCodePushReplayGuard
{
    public function alreadySeen(string $sourceId, string $replayKey): bool
    {
        return GiftCodeSourceDelivery::query()
            ->where('gift_code_source_id', $sourceId)
            ->where('replay_key', $replayKey)
            ->exists();
    }
}
