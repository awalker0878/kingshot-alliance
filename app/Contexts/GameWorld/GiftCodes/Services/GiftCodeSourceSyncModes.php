<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSourceSyncMode;

final class GiftCodeSourceSyncModes
{
    /** @return list<GiftCodeSourceSyncMode> */
    public static function all(): array
    {
        return [
            GiftCodeSourceSyncMode::Head,
            GiftCodeSourceSyncMode::Incremental,
            GiftCodeSourceSyncMode::Reconciliation,
            GiftCodeSourceSyncMode::Backfill,
        ];
    }
}
