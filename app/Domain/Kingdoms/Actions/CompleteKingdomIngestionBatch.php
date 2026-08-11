<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Kingdoms\Enums\KingdomIngestionBatchState;
use App\Domain\Kingdoms\Models\KingdomIngestionBatch;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CompleteKingdomIngestionBatch
{
    public function __construct(private OutboxRecorder $outbox) {}

    public function handle(
        string $subscriptionId,
        string $batchId,
        KingdomIngestionBatchState $state,
        ?string $failureCode = null,
    ): KingdomIngestionBatch {
        if ($state === KingdomIngestionBatchState::Pending) {
            throw ValidationException::withMessages(['state' => 'A completed batch cannot remain pending.']);
        }

        $failureCode = $this->failureCode($failureCode);
        if (
            ! in_array($state, [KingdomIngestionBatchState::Failed, KingdomIngestionBatchState::Blocked], true)
            && $failureCode !== null
        ) {
            throw ValidationException::withMessages([
                'failure_code' => 'A failure code is only valid for failed or blocked batches.',
            ]);
        }

        return DB::transaction(function () use ($subscriptionId, $batchId, $state, $failureCode): KingdomIngestionBatch {
            $subscription = KingdomIngestionSubscription::query()->lockForUpdate()->findOrFail($subscriptionId);
            $batch = KingdomIngestionBatch::query()
                ->where('subscription_id', $subscription->id)
                ->lockForUpdate()
                ->findOrFail($batchId);

            if ($batch->state !== KingdomIngestionBatchState::Pending) {
                if ($batch->state === $state && $batch->failure_code === $failureCode) {
                    return $batch;
                }

                throw ValidationException::withMessages([
                    'batch' => 'A completed ingestion batch cannot be rewritten to a different outcome.',
                ]);
            }

            $batch->forceFill([
                'state' => $state,
                'completed_at' => now(),
                'failure_code' => $failureCode,
            ])->save();

            $subscriptionUpdates = $state === KingdomIngestionBatchState::Completed
                || $state === KingdomIngestionBatchState::Partial
                ? ['last_succeeded_at' => now()]
                : ['last_failed_at' => now()];
            $subscription->forceFill($subscriptionUpdates)->save();

            $event = 'kingdoms.ingestion_batch_completed';
            $this->outbox->record(
                $event,
                (string) $batch->alliance_id,
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
                ],
                $event.':'.$batch->id,
            );

            return $batch->refresh();
        });
    }

    private function failureCode(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
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
