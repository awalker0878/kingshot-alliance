<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSourceSyncMode;
use App\Contexts\GameWorld\GiftCodes\Exceptions\GiftCodeSourceAcquisitionException;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeIngestionRun;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceHeadAcquirer;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceHealthProjector;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceSyncStateRepository;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionSweep;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeSourceRateLimit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class RunApprovedGiftCodeSourceIngestion
{
    public function __construct(
        private GiftCodeSourceAdapterRegistry $adapters,
        private IngestApprovedGiftCodeObservation $ingest,
        private GiftCodeSourceSyncStateRepository $syncStates,
        private GiftCodeSourceHealthProjector $health,
        private GiftCodeSourceHeadAcquirer $acquireHead,
    ) {}

    public function handle(
        int $sourceLimit = 10,
        ?string $afterSourceId = null,
        ?string $sourceKey = null,
    ): GiftCodeIngestionSweep {
        $startedAt = hrtime(true);
        $sourceLimit = max(1, min(100, $sourceLimit));
        if (! (bool) config('game_world.gift_codes.approved_source_ingestion', false)) {
            return $this->result($startedAt);
        }

        $rows = GiftCodeSourceRegistry::query()
            ->where('is_active', true)
            ->where('ingestion_enabled', true)
            ->where('head_poll_enabled', true)
            ->whereNull('revoked_at')
            ->where(static function (Builder $query): void {
                $query->whereNull('next_eligible_ingestion_at')
                    ->orWhere('next_eligible_ingestion_at', '<=', now());
            })
            ->when($afterSourceId !== null && $afterSourceId !== '', static fn (Builder $query) => $query->where('id', '>', $afterSourceId))
            ->when($sourceKey !== null && $sourceKey !== '', static fn (Builder $query) => $query->where('source_key', $sourceKey))
            ->orderBy('id')
            ->limit($sourceLimit + 1)
            ->get();

        $truncated = $rows->count() > $sourceLimit;
        $sources = $rows->take($sourceLimit)->values();
        $examined = 0;
        $accepted = 0;
        $duplicates = 0;
        $quarantined = 0;
        $failedSources = 0;
        $observationLimit = max(1, min(500, (int) config('game_world.gift_codes.ingestion_batch_size', 100)));
        $maxPages = max(1, min(10, (int) config('game_world.gift_codes.ingestion_max_pages_per_source', 3)));

        foreach ($sources as $source) {
            $syncState = $this->syncStates->get($source, GiftCodeSourceSyncMode::Head);
            $run = GiftCodeIngestionRun::query()->create([
                'gift_code_source_id' => (string) $source->id,
                'status' => 'running',
                'sync_mode' => GiftCodeSourceSyncMode::Head->value,
                'source_cursor' => $syncState->active_page_token ?? $syncState->committed_high_water,
                'started_at' => now(),
            ]);
            $source->forceFill([
                'last_ingestion_attempt_at' => now(),
                'last_health_checked_at' => now(),
            ])->save();

            try {
                $adapter = $this->adapters->find($source->adapter_key);
                if ($adapter === null) {
                    throw new \RuntimeException('No registered adapter matches this approved source.');
                }

                $batch = $this->acquireHead->handle(
                    $source,
                    $adapter,
                    $syncState,
                    $observationLimit,
                    $maxPages,
                );

                $runExamined = 0;
                $runAccepted = 0;
                $runDuplicates = 0;
                $runQuarantined = 0;
                $runFailureCode = null;
                $runFailureMessage = null;

                foreach ($batch->observations as $observation) {
                    $runExamined++;
                    try {
                        $result = $this->ingest->handle((string) $source->id, $observation);
                        $runAccepted += $result['accepted'] ? 1 : 0;
                        $runDuplicates += $result['duplicate'] ? 1 : 0;
                        $runQuarantined += $result['quarantined'] ? 1 : 0;
                    } catch (Throwable $exception) {
                        report($exception);
                        $runQuarantined++;
                        $runFailureCode ??= $this->observationFailureCode($exception);
                        $runFailureMessage ??= $this->observationFailureMessage(
                            $observation,
                            $runExamined,
                            $exception,
                        );
                    }
                }

                $run->forceFill([
                    'status' => $runQuarantined > 0 ? 'completed_with_quarantine' : 'completed',
                    'source_cursor' => $batch->sourceCursor,
                    'result_cursor' => $batch->resultCursor,
                    'result_checkpoint' => $batch->checkpoint?->toArray(),
                    'request_count' => $batch->requestCount,
                    'provider_request_id' => $batch->providerRequestId,
                    'retrieval_version' => $batch->retrievalVersion,
                    'quota_remaining' => $batch->rateLimit?->quotaRemaining,
                    'rate_limit_remaining' => $batch->rateLimit?->remaining,
                    'retry_after_seconds' => $batch->rateLimit?->retryAfterSeconds,
                    'examined_count' => $runExamined,
                    'accepted_count' => $runAccepted,
                    'duplicate_count' => $runDuplicates,
                    'quarantined_count' => $runQuarantined,
                    'failure_code' => $runFailureCode,
                    'failure_message' => $runFailureMessage,
                    'completed_at' => now(),
                ])->save();

                $this->syncStates->advance($syncState, $batch->syncStateChanges);
                $source->forceFill([
                    'activation_status' => 'enabled',
                    'request_count' => $source->request_count + $batch->requestCount,
                    'observation_count' => $source->observation_count + $runExamined,
                    'last_observation_at' => $runExamined > 0 ? now() : $source->last_observation_at,
                    'last_health_checked_at' => now(),
                    'last_provider_request_id' => $batch->providerRequestId,
                    'last_retrieval_version' => $batch->retrievalVersion,
                    'last_quota_remaining' => $batch->rateLimit?->quotaRemaining,
                    'last_rate_limit_remaining' => $batch->rateLimit?->remaining,
                    'last_retry_after_seconds' => $batch->rateLimit?->retryAfterSeconds,
                    'next_eligible_ingestion_at' => $this->nextEligibleFromRateLimit($batch->rateLimit),
                ])->save();
                $this->health->recordCompletedRun(
                    $source,
                    $runExamined,
                    $runAccepted,
                    $runDuplicates,
                    $runQuarantined,
                );

                $examined += $runExamined;
                $accepted += $runAccepted;
                $duplicates += $runDuplicates;
                $quarantined += $runQuarantined;
            } catch (Throwable $exception) {
                report($exception);
                $failureCode = $this->failureCode($exception);
                $requiresReview = in_array($failureCode, [
                    'unsupported_source_format',
                    'source_policy_rejected',
                ], true);
                if ($requiresReview) {
                    $quarantined++;
                } else {
                    $failedSources++;
                }

                $message = mb_substr($exception->getMessage(), 0, 2000);
                $retryAfter = $this->retryAfterSeconds($source, $exception);
                $providerRequestId = $exception instanceof GiftCodeSourceAcquisitionException
                    ? $exception->providerRequestId
                    : null;
                $run->forceFill([
                    'status' => $requiresReview ? 'completed_with_quarantine' : 'failed',
                    'request_count' => 1,
                    'provider_request_id' => $providerRequestId,
                    'retry_after_seconds' => $retryAfter,
                    'quarantined_count' => $requiresReview ? 1 : 0,
                    'failure_code' => $failureCode,
                    'failure_message' => $message,
                    'completed_at' => now(),
                ])->save();
                $source->forceFill([
                    'activation_status' => 'enabled',
                    'health_status' => $this->healthStatus($failureCode),
                    'consecutive_failures' => $source->consecutive_failures + 1,
                    'consecutive_quarantined_runs' => $requiresReview
                        ? $source->consecutive_quarantined_runs + 1
                        : $source->consecutive_quarantined_runs,
                    'quarantined_observation_count' => $source->quarantined_observation_count + ($requiresReview ? 1 : 0),
                    'last_quarantined_observation_at' => $requiresReview ? now() : $source->last_quarantined_observation_at,
                    'request_count' => $source->request_count + 1,
                    'rate_limit_event_count' => $source->rate_limit_event_count + ($failureCode === 'rate_limited' ? 1 : 0),
                    'last_health_checked_at' => now(),
                    'last_provider_request_id' => $providerRequestId,
                    'last_retry_after_seconds' => $retryAfter,
                    'next_eligible_ingestion_at' => now()->addSeconds($retryAfter),
                    'last_ingestion_failure_at' => now(),
                    'last_ingestion_failure_code' => $failureCode,
                    'last_ingestion_error' => $message,
                ])->save();
            }
        }

        $last = $sources->last();
        $next = $truncated && $last instanceof GiftCodeSourceRegistry ? (string) $last->id : null;
        $result = $this->result(
            $startedAt,
            $sources->count(),
            $examined,
            $accepted,
            $duplicates,
            $quarantined,
            $failedSources,
            $next,
        );
        Log::info('gift_codes.approved_source_ingestion_sweep', $result->toArray());

        return $result;
    }

    private function failureCode(Throwable $exception): string
    {
        if ($exception instanceof GiftCodeSourceAcquisitionException) {
            return $exception->failureCode;
        }

        return match (true) {
            str_contains($exception->getMessage(), 'No registered adapter') => 'adapter_unavailable',
            $exception instanceof \UnexpectedValueException => 'unsupported_source_format',
            $exception instanceof ValidationException => 'source_policy_rejected',
            default => 'source_retrieval_failed',
        };
    }

    private function healthStatus(string $failureCode): string
    {
        return match ($failureCode) {
            'rate_limited' => 'rate_limited',
            'authentication_failed' => 'authentication_failed',
            'permission_revoked' => 'permission_revoked',
            'source_identity_unavailable', 'provider_conflict', 'source_policy_rejected' => 'contract_changed',
            'unsupported_source_format' => 'parser_failed',
            default => 'degraded',
        };
    }

    private function retryAfterSeconds(GiftCodeSourceRegistry $source, Throwable $exception): int
    {
        if ($exception instanceof GiftCodeSourceAcquisitionException && $exception->retryAfterSeconds !== null) {
            return max(1, min(86_400, $exception->retryAfterSeconds));
        }

        $base = max(1, (int) config('game_world.gift_codes.ingestion_retry_base_seconds', 60));
        $maximum = max($base, (int) config('game_world.gift_codes.ingestion_retry_max_seconds', 3600));
        $exponent = min(6, max(0, $source->consecutive_failures));

        return min($maximum, $base * (2 ** $exponent));
    }

    private function nextEligibleFromRateLimit(?GiftCodeSourceRateLimit $rateLimit): mixed
    {
        if ($rateLimit?->retryAfterSeconds !== null && $rateLimit->retryAfterSeconds > 0) {
            return now()->addSeconds($rateLimit->retryAfterSeconds);
        }
        if ($rateLimit?->remaining === 0 && $rateLimit->resetAtUnix !== null) {
            $seconds = max(1, min(86_400, $rateLimit->resetAtUnix - time()));

            return now()->addSeconds($seconds);
        }

        return null;
    }

    private function observationFailureCode(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof \UnexpectedValueException => 'unsupported_observation_format',
            $exception instanceof ValidationException => 'observation_policy_rejected',
            default => 'observation_ingestion_failed',
        };
    }

    private function observationFailureMessage(
        GiftCodeIngestionObservation $observation,
        int $position,
        Throwable $exception,
    ): string {
        return mb_substr(sprintf(
            'Observation %d (%s, %s, evidence %s): %s',
            $position,
            trim($observation->code),
            $observation->assertion,
            $observation->rawEvidenceRef,
            $exception->getMessage(),
        ), 0, 2000);
    }

    private function result(
        int $startedAt,
        int $sourceCount = 0,
        int $examined = 0,
        int $accepted = 0,
        int $duplicates = 0,
        int $quarantined = 0,
        int $failedSources = 0,
        ?string $nextSourceCursor = null,
    ): GiftCodeIngestionSweep {
        return new GiftCodeIngestionSweep(
            $sourceCount,
            $examined,
            $accepted,
            $duplicates,
            $quarantined,
            $failedSources,
            $nextSourceCursor,
            (int) round((hrtime(true) - $startedAt) / 1_000_000),
        );
    }
}
