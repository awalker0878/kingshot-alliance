<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Services;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionSubscription;
use App\Contexts\Intelligence\Ingestion\ValueObjects\KingdomIngestionMutationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/** Ingestion-owned lock boundary with immutable Alliance state from the owner context. */
final readonly class KingdomIngestionMutationState
{
    public function __construct(private AllianceReferenceQuery $alliances) {}

    public function lockSubscription(string $subscriptionId): KingdomIngestionMutationContext
    {
        return $this->acquire($subscriptionId, false)
            ?? throw new LogicException('Required ingestion subscription mutation state was not acquired.');
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

        $route = KingdomIngestionSubscription::query()->select(['id', 'alliance_id'])->whereKey($subscriptionId)->first();
        if (! $route instanceof KingdomIngestionSubscription) {
            if ($nullable) { return null; }
            KingdomIngestionSubscription::query()->findOrFail($subscriptionId);
        }

        $alliance = $this->alliances->lockCurrent((string) $route->alliance_id);
        if (! $alliance->active()) {
            if ($nullable) { return null; }
            throw ValidationException::withMessages(['subscription' => 'Automated Kingdom ingestion is unavailable while the Alliance is not active.']);
        }

        $query = KingdomIngestionSubscription::query()->whereKey($route->id)->where('alliance_id', $alliance->allianceId)->lockForUpdate();
        $subscription = $nullable ? $query->first() : $query->firstOrFail();
        if (! $subscription instanceof KingdomIngestionSubscription) { return null; }

        return new KingdomIngestionMutationContext($alliance, $subscription);
    }
}
