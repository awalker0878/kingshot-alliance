<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Actions;

use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionSubscriptionState;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionSubscription;
use App\Contexts\Intelligence\Ingestion\Services\KingdomIngestionAdapterRegistry;
use Illuminate\Support\Facades\DB;

final readonly class ReconcileKingdomIngestionSources
{
    public function __construct(private KingdomIngestionAdapterRegistry $adapters) {}

    public function handle(int $limit = 500): int
    {
        $limit = max(1, min(2000, $limit));
        $ids = KingdomIngestionSubscription::query()
            ->whereIn('state', [
                KingdomIngestionSubscriptionState::Active->value,
                KingdomIngestionSubscriptionState::Paused->value,
            ])
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $revoked = 0;

        foreach ($ids as $id) {
            $changed = DB::transaction(function () use ($id): bool {
                $subscription = KingdomIngestionSubscription::query()->lockForUpdate()->find($id);
                if (! $subscription instanceof KingdomIngestionSubscription) {
                    return false;
                }

                if (! in_array($subscription->state, [
                    KingdomIngestionSubscriptionState::Active,
                    KingdomIngestionSubscriptionState::Paused,
                ], true)) {
                    return false;
                }

                $adapter = $this->adapters->acquisition($subscription->adapter_key);
                if ($adapter !== null && $adapter->version() === $subscription->adapter_version) {
                    return false;
                }

                $subscription->forceFill([
                    'state' => KingdomIngestionSubscriptionState::Disabled,
                    'blocked_at' => now(),
                    'blocked_reason' => 'source_unapproved',
                    'last_failure_code' => 'source_unapproved',
                    'next_run_at' => null,
                    'circuit_open_until' => null,
                ])->save();

                return true;
            });

            if ($changed) {
                $revoked++;
            }
        }

        return $revoked;
    }
}
