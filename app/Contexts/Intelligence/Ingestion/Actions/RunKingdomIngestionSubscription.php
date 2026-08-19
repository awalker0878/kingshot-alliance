<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Actions;

use App\Contexts\Intelligence\Ingestion\Contracts\KingdomIngestionAcquisitionAdapter;
use App\Contexts\Intelligence\Ingestion\Data\KingdomIngestionAcquisitionPage;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionBatchState;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionCandidateState;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionSubscriptionState;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionTargetKind;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionBatch;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionCandidate;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionSubscription;
use App\Contexts\Intelligence\Ingestion\Services\KingdomIngestionAdapterRegistry;
use App\Contexts\Intelligence\Ingestion\Services\KingdomIngestionMutationState;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

final readonly class RunKingdomIngestionSubscription
{
    public function __construct(
        private KingdomIngestionMutationState $mutations,
        private KingdomIngestionAdapterRegistry $adapters,
        private StartKingdomIngestionBatch $startBatch,
        private StageKingdomIngestionCandidate $stageCandidate,
        private PromoteKingdomIngestionPlayerSnapshot $promotePlayer,
        private PromoteKingdomIngestionAllianceObservation $promoteAlliance,
        private CompleteKingdomIngestionBatch $completeBatch,
    ) {}

    public function handle(string $subscriptionId): ?string
    {
        $subscription = $this->runnableSubscription($subscriptionId);
        if (! $subscription instanceof KingdomIngestionSubscription) {
            return null;
        }

        $adapter = $this->adapters->requireAcquisition($subscription->adapter_key);

        try {
            // Source/network acquisition deliberately stays outside database lock scope.
            $page = $adapter->acquire($subscription->source_cursor, KingdomIngestionAcquisitionPage::MAX_RECORDS);
            $batchId = $this->startBatch->handle($subscriptionId, $page->sourceWindowId);
            $batch = $this->batch($subscriptionId, $batchId);

            if ($batch->state !== KingdomIngestionBatchState::Pending) {
                if (! in_array($batch->state, [KingdomIngestionBatchState::Completed, KingdomIngestionBatchState::Partial], true)) {
                    throw ValidationException::withMessages([
                        'batch' => 'That source window already has a terminal ingestion outcome and cannot be rewritten.',
                    ]);
                }
                if ($batch->next_source_cursor !== $page->nextCursor) {
                    throw ValidationException::withMessages([
                        'batch' => 'The source returned a different next cursor for an already completed source window.',
                    ]);
                }

                $this->advanceCursor($subscriptionId, $batch, $adapter);

                return (string) $batch->id;
            }

            DB::transaction(function () use ($subscriptionId, $batchId, $page): void {
                $context = $this->mutations->lockSubscription($subscriptionId);
                $lockedBatch = KingdomIngestionBatch::query()
                    ->where('subscription_id', $context->subscription->id)
                    ->whereKey($batchId)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($lockedBatch->state !== KingdomIngestionBatchState::Pending) {
                    throw ValidationException::withMessages([
                        'batch' => 'Only a pending ingestion batch can capture its next source cursor.',
                    ]);
                }
                $lockedBatch->forceFill(['next_source_cursor' => $page->nextCursor])->save();
            });

            foreach ($page->records as $record) {
                $candidateId = $this->stageCandidate->handle($subscriptionId, $batchId, $record);
                $candidate = $this->candidate($subscriptionId, $batchId, $candidateId);
                if ($candidate->state !== KingdomIngestionCandidateState::Pending) {
                    continue;
                }

                match ($candidate->target_kind) {
                    KingdomIngestionTargetKind::PlayerSnapshot => $this->promotePlayer->handle($subscriptionId, $candidateId),
                    KingdomIngestionTargetKind::AllianceObservation => $this->promoteAlliance->handle($subscriptionId, $candidateId),
                };
            }

            $batch = $this->batch($subscriptionId, $batchId);
            $outcome = $batch->records_quarantined > 0 || $batch->records_rejected > 0
                ? KingdomIngestionBatchState::Partial
                : KingdomIngestionBatchState::Completed;
            $completedBatchId = $this->completeBatch->handle($subscriptionId, $batchId, $outcome);
            $completedBatch = $this->batch($subscriptionId, $completedBatchId);
            $this->advanceCursor($subscriptionId, $completedBatch, $adapter);

            return $completedBatchId;
        } catch (Throwable $exception) {
            $this->recordFailure($subscriptionId, $this->failureCode($exception));
            throw $exception;
        }
    }

    private function batch(string $subscriptionId, string $batchId): KingdomIngestionBatch
    {
        return KingdomIngestionBatch::query()
            ->where('subscription_id', $subscriptionId)
            ->whereKey($batchId)
            ->firstOrFail();
    }

    private function candidate(string $subscriptionId, string $batchId, string $candidateId): KingdomIngestionCandidate
    {
        return KingdomIngestionCandidate::query()
            ->where('subscription_id', $subscriptionId)
            ->where('batch_id', $batchId)
            ->whereKey($candidateId)
            ->firstOrFail();
    }

    private function runnableSubscription(string $subscriptionId): ?KingdomIngestionSubscription
    {
        return DB::transaction(function () use ($subscriptionId): ?KingdomIngestionSubscription {
            $context = $this->mutations->lockSubscription($subscriptionId);
            $subscription = $context->subscription;
            if ($subscription->state !== KingdomIngestionSubscriptionState::Active) {
                return null;
            }
            if ($subscription->circuit_open_until !== null && $subscription->circuit_open_until->isFuture()) {
                return null;
            }
            if ($context->alliance->kingdomId === null
                || (string) $context->alliance->kingdomId !== (string) $subscription->kingdom_id) {
                $this->block($subscription, 'kingdom_context_changed');

                return null;
            }

            $adapter = $this->adapters->acquisition($subscription->adapter_key);
            if ($adapter === null || $adapter->version() !== $subscription->adapter_version) {
                $this->block($subscription, 'source_unapproved');

                return null;
            }

            return $subscription->refresh();
        });
    }

    private function advanceCursor(
        string $subscriptionId,
        KingdomIngestionBatch $batch,
        KingdomIngestionAcquisitionAdapter $adapter,
    ): void {
        DB::transaction(function () use ($subscriptionId, $batch, $adapter): void {
            $context = $this->mutations->lockSubscription($subscriptionId);
            $subscription = $context->subscription;
            $lockedBatch = KingdomIngestionBatch::query()
                ->where('subscription_id', $subscription->id)
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedBatch->state, [KingdomIngestionBatchState::Completed, KingdomIngestionBatchState::Partial], true)) {
                throw ValidationException::withMessages([
                    'batch' => 'The ingestion cursor can advance only after a successful or partial batch outcome.',
                ]);
            }
            if ($subscription->source_cursor !== $lockedBatch->source_cursor
                && $subscription->source_cursor !== $lockedBatch->next_source_cursor) {
                throw ValidationException::withMessages([
                    'subscription' => 'The ingestion cursor changed concurrently; automatic rewind is blocked.',
                ]);
            }

            $subscription->forceFill([
                'source_cursor' => $lockedBatch->next_source_cursor,
                'next_run_at' => now()->addSeconds($adapter->pollIntervalSeconds()),
                'last_succeeded_at' => now(),
                'consecutive_failures' => 0,
                'circuit_open_until' => null,
                'last_failure_code' => null,
                'blocked_at' => null,
                'blocked_reason' => null,
            ])->save();
        });
    }

    private function recordFailure(string $subscriptionId, string $failureCode): void
    {
        DB::transaction(function () use ($subscriptionId, $failureCode): void {
            $context = $this->mutations->lockSubscriptionOrNull($subscriptionId);
            if ($context === null) {
                return;
            }
            $subscription = $context->subscription;

            $failures = min(65535, $subscription->consecutive_failures + 1);
            $backoffSeconds = (int) min(3600, 60 * (2 ** min(5, max(0, $failures - 1))));
            $circuitSeconds = $failures >= 3
                ? (int) min(3600, 300 * (2 ** min(4, $failures - 3)))
                : null;
            $circuitUntil = $circuitSeconds === null ? null : now()->addSeconds($circuitSeconds);

            $subscription->forceFill([
                'last_failed_at' => now(),
                'consecutive_failures' => $failures,
                'circuit_open_until' => $circuitUntil,
                'last_failure_code' => $failureCode,
                'blocked_at' => now(),
                'blocked_reason' => $failureCode,
                'next_run_at' => $circuitUntil ?? now()->addSeconds($backoffSeconds),
            ])->save();
        });
    }

    private function block(KingdomIngestionSubscription $subscription, string $reason): void
    {
        $pending = KingdomIngestionBatch::query()
            ->where('subscription_id', $subscription->id)
            ->where('state', KingdomIngestionBatchState::Pending->value)
            ->orderByDesc('started_at')
            ->lockForUpdate()
            ->first();
        if ($pending instanceof KingdomIngestionBatch) {
            $this->completeBatch->handle(
                (string) $subscription->id,
                (string) $pending->id,
                KingdomIngestionBatchState::Blocked,
                $reason,
            );
            $subscription->refresh();
        }

        $subscription->forceFill([
            'blocked_at' => now(),
            'blocked_reason' => $reason,
            'last_failure_code' => $reason,
            'next_run_at' => now()->addMinutes(15),
        ])->save();
    }

    private function failureCode(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof InvalidArgumentException => 'source_contract_invalid',
            $exception instanceof ValidationException => 'processing_validation_failed',
            default => 'acquisition_failed',
        };
    }
}
