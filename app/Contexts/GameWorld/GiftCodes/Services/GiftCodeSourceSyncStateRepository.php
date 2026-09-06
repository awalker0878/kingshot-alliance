<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSourceSyncMode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSyncState;

final class GiftCodeSourceSyncStateRepository
{
    public function get(GiftCodeSourceRegistry $source, GiftCodeSourceSyncMode $mode): GiftCodeSourceSyncState
    {
        return GiftCodeSourceSyncState::query()->firstOrCreate(
            [
                'gift_code_source_id' => (string) $source->id,
                'sync_mode' => $mode->value,
            ],
            ['version' => 0],
        );
    }

    /** @param array<string,mixed> $changes */
    public function advance(GiftCodeSourceSyncState $state, array $changes): GiftCodeSourceSyncState
    {
        $state->forceFill([
            ...$changes,
            'version' => $state->version + 1,
        ])->save();

        return $state->refresh();
    }
}
