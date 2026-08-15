<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Contexts\GameWorld\Models\KingdomIngestionSubscription;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use App\Domain\Kingdoms\Jobs\RunKingdomIngestionSubscriptionJob;
use App\Domain\Kingdoms\Services\KingdomIngestionAdapterRegistry;
use Illuminate\Support\Facades\DB;

final readonly class QueueDueKingdomIngestionSubscriptions
{
    public function __construct(private KingdomIngestionAdapterRegistry $adapters) {}

    public function handle(int $limit = 100): int
    {
        $limit = max(1, min(500, $limit));
        $ids = KingdomIngestionSubscription::query()
            ->where('state', KingdomIngestionSubscriptionState::Active->value)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->where(static function ($query): void {
                $query->whereNull('circuit_open_until')->orWhere('circuit_open_until', '<=', now());
            })
            ->orderBy('next_run_at')
            ->limit($limit)
            ->pluck('id');

        $queued = 0;

        foreach ($ids as $id) {
            $claimed = DB::transaction(function () use ($id): bool {
                $subscription = KingdomIngestionSubscription::query()->lockForUpdate()->find($id);
                if (! $subscription instanceof KingdomIngestionSubscription) {
                    return false;
                }

                if ($subscription->state !== KingdomIngestionSubscriptionState::Active) {
                    return false;
                }

                if ($subscription->next_run_at === null || $subscription->next_run_at->isFuture()) {
                    return false;
                }

                if ($subscription->circuit_open_until !== null && $subscription->circuit_open_until->isFuture()) {
                    $subscription->forceFill(['next_run_at' => $subscription->circuit_open_until])->save();

                    return false;
                }

                $adapter = $this->adapters->acquisition($subscription->adapter_key);
                if ($adapter === null || $adapter->version() !== $subscription->adapter_version) {
                    $subscription->forceFill([
                        'blocked_at' => now(),
                        'blocked_reason' => 'source_unapproved',
                        'last_failure_code' => 'source_unapproved',
                        'next_run_at' => now()->addMinutes(15),
                    ])->save();

                    return false;
                }

                $subscription->forceFill([
                    'last_claimed_at' => now(),
                    'next_run_at' => now()->addSeconds($adapter->pollIntervalSeconds()),
                ])->save();

                RunKingdomIngestionSubscriptionJob::dispatch((string) $subscription->id)
                    ->onQueue('kingdoms-ingestion')
                    ->afterCommit();

                return true;
            });

            if ($claimed) {
                $queued++;
            }
        }

        return $queued;
    }
}
