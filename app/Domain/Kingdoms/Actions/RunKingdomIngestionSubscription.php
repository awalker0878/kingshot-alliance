<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Contracts\KingdomIngestionAcquisitionAdapter;
use App\Domain\Kingdoms\Data\KingdomIngestionAcquisitionPage;
use App\Domain\Kingdoms\Enums\KingdomIngestionBatchState;
use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use App\Domain\Kingdoms\Enums\KingdomIngestionTargetKind;
use App\Domain\Kingdoms\Models\KingdomIngestionBatch;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Kingdoms\Services\KingdomIngestionAdapterRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

final readonly class RunKingdomIngestionSubscription
{
    public function __construct(
        private KingdomIngestionAdapterRegistry $adapters,
        private StartKingdomIngestionBatch $startBatch,
        private StageKingdomIngestionCandidate $stageCandidate,
        private PromoteKingdomIngestionPlayerSnapshot $promotePlayer,
        private PromoteKingdomIngestionAllianceObservation $promoteAlliance,
        private CompleteKingdomIngestionBatch $completeBatch,
    ) {}

    public function handle(string $subscriptionId): ?KingdomIngestionBatch
    {
        $subscription = $this->runnableSubscription($subscriptionId);
        if (! $subscription instanceof KingdomIngestionSubscription) {
            return null;
        }

        $adapter = $this->adapters->requireAcquisition($subscription->adapter_key);

        try {
            $page = $adapter->acquire($subscription->source_cursor, KingdomIngestionAcquisitionPage::MAX_RECORDS);
            $batch = $this->startBatch->handle($subscriptionId, $page->sourceWindowId);

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

                return $batch->refresh();
            }

            $batch->forceFill(['next_source_cursor' => $page->nextCursor])->save();

            foreach ($page->records as $record) {
                $candidate = $this->stageCandidate->handle($subscriptionId, (string) $batch->id, $record);
                if ($candidate->state !== KingdomIngestionCandidateState::Pending) {
                    continue;
                }

                match ($candidate->target_kind) {
                    KingdomIngestionTargetKind::PlayerSnapshot => $this->promotePlayer->handle(
                        $subscriptionId,
                        (string) $candidate->id,
                    ),
                    KingdomIngestionTargetKind::AllianceObservation => $this->promoteAlliance->handle(
                        $subscriptionId,
                        (string) $candidate->id,
                    ),
                };
            }

            $batch->refresh();
            $outcome = $batch->records_quarantined > 0 || $batch->records_rejected > 0
                ? KingdomIngestionBatchState::Partial
                : KingdomIngestionBatchState::Completed;
            $batch = $this->completeBatch->handle($subscriptionId, (string) $batch->id, $outcome);
            $this->advanceCursor($subscriptionId, $batch, $adapter);

            return $batch->refresh();
        } catch (Throwable $exception) {
            $this->recordFailure($subscriptionId, $this->failureCode($exception));

            throw $exception;
        }
    }

    private function runnableSubscription(string $subscriptionId): ?KingdomIngestionSubscription
    {
        return DB::transaction(function () use ($subscriptionId): ?KingdomIngestionSubscription {
            $subscription = KingdomIngestionSubscription::query()->lockForUpdate()->findOrFail($subscriptionId);
            if ($subscription->state !== KingdomIngestionSubscriptionState::Active) {
                return null;
            }

            if ($subscription->circuit_open_until !== null && $subscription->circuit_open_until->isFuture()) {
                return null;
            }

            $alliance = Alliance::query()->lockForUpdate()->findOrFail($subscription->alliance_id);
            if ($alliance->kingdom_id === null || $alliance->kingdom_id !== $subscription->kingdom_id) {
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
            $subscription = KingdomIngestionSubscription::query()->lockForUpdate()->findOrFail($subscriptionId);
            $lockedBatch = KingdomIngestionBatch::query()
                ->where('subscription_id', $subscription->id)
                ->lockForUpdate()
                ->findOrFail($batch->id);

            if (! in_array($lockedBatch->state, [KingdomIngestionBatchState::Completed, KingdomIngestionBatchState::Partial], true)) {
                throw ValidationException::withMessages([
                    'batch' => 'The ingestion cursor can advance only after a successful or partial batch outcome.',
                ]);
            }

            if (
                $subscription->source_cursor !== $lockedBatch->source_cursor
                && $subscription->source_cursor !== $lockedBatch->next_source_cursor
            ) {
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
            $subscription = KingdomIngestionSubscription::query()->lockForUpdate()->find($subscriptionId);
            if (! $subscription instanceof KingdomIngestionSubscription) {
                return;
            }

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
            ->lockForUpdate()
            ->latest('started_at')
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
