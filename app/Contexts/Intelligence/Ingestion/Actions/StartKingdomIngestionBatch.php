<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Actions;

use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionBatchState;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionSubscriptionState;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionBatch;
use App\Contexts\Intelligence\Ingestion\Services\KingdomIngestionAdapterRegistry;
use App\Contexts\Intelligence\Ingestion\Services\KingdomIngestionMutationState;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class StartKingdomIngestionBatch
{
    public function __construct(
        private KingdomIngestionMutationState $mutations,
        private KingdomIngestionAdapterRegistry $adapters,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $subscriptionId, ?string $sourceWindowId = null): KingdomIngestionBatch
    {
        $sourceWindowId = $this->nullableIdentifier($sourceWindowId, 191, 'source_window_id');

        return DB::transaction(function () use ($subscriptionId, $sourceWindowId): KingdomIngestionBatch {
            $context = $this->mutations->lockSubscription($subscriptionId);
            $subscription = $context->subscription;

            if ($subscription->state !== KingdomIngestionSubscriptionState::Active) {
                throw ValidationException::withMessages([
                    'subscription' => 'Only active ingestion subscriptions can start a batch.',
                ]);
            }

            if ($context->alliance->kingdom_id === null
                || (string) $context->alliance->kingdom_id !== (string) $subscription->kingdom_id) {
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

            try {
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
            } catch (QueryException $exception) {
                if ($sourceWindowId !== null) {
                    $existing = KingdomIngestionBatch::query()
                        ->where('subscription_id', $subscription->id)
                        ->where('source_window_id', $sourceWindowId)
                        ->first();
                    if ($existing instanceof KingdomIngestionBatch) {
                        return $existing;
                    }
                }

                throw $exception;
            }

            $metadata = [
                'subscription_id' => (string) $subscription->id,
                'batch_id' => (string) $batch->id,
                'kingdom_id' => (string) $batch->kingdom_id,
                'adapter_key' => $batch->adapter_key,
                'adapter_version' => $batch->adapter_version,
                'source_window_id' => $batch->source_window_id,
            ];

            $this->outbox->record(
                'intelligence.ingestion_batch_started',
                (string) $batch->alliance_id,
                $batch,
                $metadata,
                'intelligence.ingestion_batch_started:'.$batch->id,
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
