<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeProviderSyncPlanner
{
    public function plan(string $provider, bool $pushEnabled): GiftCodeProviderSyncPlan
    {
        return new GiftCodeProviderSyncPlan(
            provider: $provider,
            headFirst: true,
            continueBackfill: true,
            reconcile: true,
        );
    }
}
