<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeSourceSyncMode: string
{
    case Head = 'head';
    case Incremental = 'incremental';
    case Reconciliation = 'reconciliation';
    case Backfill = 'backfill';
}
