<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final readonly class GiftCodeProviderSyncPlan
{
    public function __construct(
        public string $provider,
        public bool $headFirst,
        public bool $continueBackfill,
        public bool $reconcile,
    ) {}
}
