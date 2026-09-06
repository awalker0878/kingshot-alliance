<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeSourceFeaturePolicy
{
    /** @param array<string, mixed> $policy */
    public function pushEnabled(array $policy): bool
    {
        return ($policy['push_enabled'] ?? false) === true;
    }

    /** @param array<string, mixed> $policy */
    public function headPollEnabled(array $policy): bool
    {
        return ($policy['head_poll_enabled'] ?? true) === true;
    }

    /** @param array<string, mixed> $policy */
    public function reconciliationEnabled(array $policy): bool
    {
        return ($policy['reconciliation_enabled'] ?? true) === true;
    }

    /** @param array<string, mixed> $policy */
    public function backfillEnabled(array $policy): bool
    {
        return ($policy['backfill_enabled'] ?? true) === true;
    }
}
