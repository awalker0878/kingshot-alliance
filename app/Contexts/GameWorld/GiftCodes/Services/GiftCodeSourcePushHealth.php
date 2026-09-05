<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSubscription;

final class GiftCodeSourcePushHealth
{
    public function isOperational(GiftCodeSourceSubscription $subscription): bool
    {
        if ($subscription->status !== 'active') {
            return false;
        }

        return $subscription->expires_at === null || $subscription->expires_at->isFuture();
    }
}
