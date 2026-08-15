<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Services;

use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\KingdomIngestionSubscription;
use App\Domain\Kingdoms\ValueObjects\KingdomIngestionMutationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/** Kingdoms-owned lock-order boundary for automated ingestion state. */
final readonly class KingdomIngestionMutationState
{
    public function lockSubscription(string $subscriptionId): KingdomIngestionMutationContext
    {
        return $this->acquire($subscriptionId, false);
    }

    public function lockSubscriptionOrNull(string $subscriptionId): ?KingdomIngestionMutationContext
    {
        return $this->acquire($subscriptionId, true);
    }

    private function acquire(string $subscriptionId, bool $nullable): ?KingdomIngestionMutationContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Kingdom ingestion mutation state must be acquired inside a database transaction.');
        }

        $routeQuery = KingdomIngestionSubscription::query()
            ->select(['id', 'alliance_id'])
            ->whereKey($subscriptionId);
        $route = $nullable ? $routeQuery->first() : $routeQuery->firstOrFail();
        if (! $route instanceof KingdomIngestionSubscription) {
            return null;
        }

        $allianceQuery = Alliance::query()->whereKey($route->alliance_id)->sharedLock();
        $alliance = $nullable ? $allianceQuery->first() : $allianceQuery->firstOrFail();
        if (! $alliance instanceof Alliance) {
            return null;
        }
        if ($alliance->status !== AllianceStatus::Active) {
            if ($nullable) {
                return null;
            }
            throw ValidationException::withMessages([
                'subscription' => 'Automated Kingdom ingestion is unavailable while the Alliance is not active.',
            ]);
        }

        $subscriptionQuery = KingdomIngestionSubscription::query()
            ->whereKey($route->id)
            ->where('alliance_id', $alliance->id)
            ->lockForUpdate();
        $subscription = $nullable ? $subscriptionQuery->first() : $subscriptionQuery->firstOrFail();
        if (! $subscription instanceof KingdomIngestionSubscription) {
            return null;
        }

        return new KingdomIngestionMutationContext($alliance, $subscription);
    }
}
