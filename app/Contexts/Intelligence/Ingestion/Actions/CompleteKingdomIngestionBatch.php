<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Actions;

use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionBatchState;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionBatch;
use App\Contexts\Intelligence\Ingestion\Services\KingdomIngestionMutationState;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CompleteKingdomIngestionBatch
{
    public function __construct(
        private KingdomIngestionMutationState $mutations,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $subscriptionId,
        string $batchId,
        KingdomIngestionBatchState $state,
        ?string $failureCode = null,
    ): string {
        if ($state === KingdomIngestionBatchState::Pending) {
            throw ValidationException::withMessages(['state' => 'A completed batch cannot remain pending.']);
        }

        $failureCode = $this->failureCode($failureCode);
        if (! in_array($state, [KingdomIngestionBatchState::Failed, KingdomIngestionBatchState::Blocked], true)
            && $failureCode !== null) {
            throw ValidationException::withMessages([
                'failure_code' => 'A failure code is only valid for failed or blocked batches.',
            ]);
        }

        return DB::transaction(function () use ($subscriptionId, $batchId, $state, $failureCode): string {
            $context = $this->mutations->lockSubscription($subscriptionId);
            $subscription = $context->subscription;
            $batch = KingdomIngestionBatch::query()
                ->where('subscription_id', $subscription->id)
                ->whereKey($batchId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($batch->state !== KingdomIngestionBatchState::Pending) {
                if ($batch->state === $state && $batch->failure_code === $failureCode) {
                    return (string) $batch->id;
                }

                throw ValidationException::withMessages([
                    'batch' => 'A completed ingestion batch cannot be rewritten to a different outcome.',
                ]);
            }

            $completedAt = now();
            $batch->forceFill([
                'state' => $state,
                'completed_at' => $completedAt,
                'failure_code' => $failureCode,
            ])->save();

            $subscription->forceFill(
                $state === KingdomIngestionBatchState::Completed || $state === KingdomIngestionBatchState::Partial
                    ? ['last_succeeded_at' => $completedAt]
                    : ['last_failed_at' => $completedAt],
            )->save();

            $event = 'intelligence.ingestion_batch_completed';
            $this->outbox->record(
                $event,
                (string) $context->alliance->allianceId,
                $batch,
                [
                    'subscription_id' => (string) $subscription->id,
                    'batch_id' => (string) $batch->id,
                    'state' => $state->value,
                    'failure_code' => $failureCode,
                    'records_received' => $batch->records_received,
                    'records_staged' => $batch->records_staged,
                    'records_quarantined' => $batch->records_quarantined,
                    'records_rejected' => $batch->records_rejected,
                    'origin' => 'system',
                ],
                $event.':'.$batch->id,
            );

            return (string) $batch->id;
        });
    }

    private function failureCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $value) !== 1) {
            throw ValidationException::withMessages([
                'failure_code' => 'The batch failure code must be a stable lowercase identifier.',
            ]);
        }

        return $value;
    }
}
