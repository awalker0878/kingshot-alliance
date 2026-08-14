<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Services;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Kingdoms\ValueObjects\KingdomIngestionMutationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Kingdoms-owned lock-order boundary for automated ingestion state. */
final readonly class KingdomIngestionMutationState
{
    public function lockSubscription(string $subscriptionId): KingdomIngestionMutationContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Kingdom ingestion mutation state must be acquired inside a database transaction.');
        }

        $route = KingdomIngestionSubscription::query()
            ->select(['id', 'alliance_id'])
            ->whereKey($subscriptionId)
            ->firstOrFail();

        $alliance = Alliance::query()
            ->whereKey($route->alliance_id)
            ->sharedLock()
            ->firstOrFail();

        $subscription = KingdomIngestionSubscription::query()
            ->whereKey($route->id)
            ->where('alliance_id', $alliance->id)
            ->lockForUpdate()
            ->firstOrFail();

        return new KingdomIngestionMutationContext($alliance, $subscription);
    }
}
