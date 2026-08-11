<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Enums\KingdomIngestionBatchState;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use App\Domain\Kingdoms\Models\KingdomIngestionBatch;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Kingdoms\Services\KingdomIngestionAdapterRegistry;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class StartKingdomIngestionBatch
{
    public function __construct(
        private KingdomIngestionAdapterRegistry $adapters,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $subscriptionId, ?string $sourceWindowId = null): KingdomIngestionBatch
    {
        $sourceWindowId = $this->nullableIdentifier($sourceWindowId, 191, 'source_window_id');

        return DB::transaction(function () use ($subscriptionId, $sourceWindowId): KingdomIngestionBatch {
            $subscription = KingdomIngestionSubscription::query()->lockForUpdate()->findOrFail($subscriptionId);
            $alliance = Alliance::query()->lockForUpdate()->findOrFail($subscription->alliance_id);

            if ($subscription->state !== KingdomIngestionSubscriptionState::Active) {
                throw ValidationException::withMessages([
                    'subscription' => 'Only active ingestion subscriptions can start a batch.',
                ]);
            }

            if ($alliance->kingdom_id === null || $alliance->kingdom_id !== $subscription->kingdom_id) {
                throw ValidationException::withMessages([
                    'subscription' => 'Ingestion is blocked because the alliance Kingdom no longer matches the subscription context.',
                ]);
            }

            $adapter = $this->adapters->require($subscription->adapter_key);
            if ($adapter->version() !== $subscription->adapter_version) {
                throw ValidationException::withMessages([
                    'subscription' => 'Ingestion is blocked because the configured adapter version is no longer approved.',
                ]);
            }

            if ($sourceWindowId !== null) {
                $existing = KingdomIngestionBatch::query()
                    ->where('subscription_id', $subscription->id)
                    ->where('source_window_id', $sourceWindowId)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof KingdomIngestionBatch) {
                    return $existing;
                }
            }

            $batch = KingdomIngestionBatch::query()->create([
                'subscription_id' => $subscription->id,
                'alliance_id' => $subscription->alliance_id,
                'kingdom_id' => $subscription->kingdom_id,
                'adapter_key' => $subscription->adapter_key,
                'adapter_version' => $subscription->adapter_version,
                'source_cursor' => $subscription->source_cursor,
                'source_window_id' => $sourceWindowId,
                'state' => KingdomIngestionBatchState::Pending,
                'started_at' => now(),
            ]);

            $metadata = [
                'subscription_id' => (string) $subscription->id,
                'batch_id' => (string) $batch->id,
                'kingdom_id' => (string) $batch->kingdom_id,
                'adapter_key' => $batch->adapter_key,
                'adapter_version' => $batch->adapter_version,
                'source_window_id' => $batch->source_window_id,
            ];

            $this->outbox->record(
                'kingdoms.ingestion_batch_started',
                (string) $batch->alliance_id,
                $batch,
                $metadata,
                'kingdoms.ingestion_batch_started:'.$batch->id,
            );

            return $batch;
        });
    }

    private function nullableIdentifier(?string $value, int $max, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => 'The source identifier is too long.']);
        }

        return $value;
    }
}
