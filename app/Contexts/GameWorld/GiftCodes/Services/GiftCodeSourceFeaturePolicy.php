<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeSourceFeaturePolicy
{
    public function pushEnabled(array $policy): bool
    {
        return ($policy['push_enabled'] ?? false) === true;
    }

    public function headPollEnabled(array $policy): bool
    {
        return ($policy['head_poll_enabled'] ?? true) === true;
    }

    public function reconciliationEnabled(array $policy): bool
    {
        return ($policy['reconciliation_enabled'] ?? true) === true;
    }

    public function backfillEnabled(array $policy): bool
    {
        return ($policy['backfill_enabled'] ?? true) === true;
    }
}
