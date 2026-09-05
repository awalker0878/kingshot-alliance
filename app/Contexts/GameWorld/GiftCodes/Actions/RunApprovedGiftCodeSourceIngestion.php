<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Exceptions\GiftCodeSourceAcquisitionException;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeIngestionRun;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionSweep;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeSourceCheckpoint;
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
            $run = GiftCodeIngestionRun::query()->create([
                'gift_code_source_id' => (string) $source->id,
                'status' => 'running',
                'source_cursor' => $source->ingestion_cursor,
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

                $runExamined = 0;
                $runAccepted = 0;
                $runDuplicates = 0;
                $runQuarantined = 0;
                $runFailureCode = null;
                $runFailureMessage = null;
                $requestCount = 0;
                $providerRequestId = null;
                $retrievalVersion = null;
                $rateLimit = null;
                $checkpoint = null;
                $cursor = $source->ingestion_cursor;
                $resultCursor = $cursor;
                $seenCursors = [];

                for ($pageNumber = 0; $pageNumber < $maxPages && $runExamined < $observationLimit; $pageNumber++) {
                    if ($cursor !== null && isset($seenCursors[$cursor])) {
                        throw new \UnexpectedValueException('The source adapter repeated an ingestion cursor.');
                    }
                    if ($cursor !== null) {
                        $seenCursors[$cursor] = true;
                    }

                    $remaining = max(1, $observationLimit - $runExamined);
                    $page = $adapter->acquire($source, $cursor, $remaining);
                    if (count($page->observations) > $remaining) {
                        throw new \UnexpectedValueException('The source adapter exceeded the bounded observation limit.');
                    }
                    $requestCount += max(1, $page->requestCount);
                    $providerRequestId = $page->providerRequestId ?? $providerRequestId;
                    $retrievalVersion = $page->retrievalVersion ?? $retrievalVersion;
                    $rateLimit = $page->rateLimit ?? $rateLimit;
                    $checkpoint = $page->checkpoint ?? new GiftCodeSourceCheckpoint(
                        cursor: $page->nextCursor,
                        retrievalVersion: $page->retrievalVersion,
                        providerRequestId: $page->providerRequestId,
                    );

                    foreach ($page->observations as $observation) {
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

                    $resultCursor = $page->nextCursor;
                    if ($page->nextCursor === null || $page->nextCursor === $cursor) {
                        break;
                    }
                    $cursor = $page->nextCursor;
                }

                $status = $runQuarantined > 0 ? 'completed_with_quarantine' : 'completed';
                $run->forceFill([
                    'status' => $status,
                    'result_cursor' => $resultCursor,
                    'result_checkpoint' => $checkpoint?->toArray(),
                    'request_count' => $requestCount,
                    'provider_request_id' => $providerRequestId,
                    'retrieval_version' => $retrievalVersion,
                    'quota_remaining' => $rateLimit?->quotaRemaining,
                    'rate_limit_remaining' => $rateLimit?->remaining,
                    'retry_after_seconds' => $rateLimit?->retryAfterSeconds,
                    'examined_count' => $runExamined,
                    'accepted_count' => $runAccepted,
                    'duplicate_count' => $runDuplicates,
                    'quarantined_count' => $runQuarantined,
                    'failure_code' => $runFailureCode,
                    'failure_message' => $runFailureMessage,
                    'completed_at' => now(),
                ])->save();

                $nextEligibleAt = $this->nextEligibleFromRateLimit($rateLimit);
                $source->forceFill([
                    'ingestion_cursor' => $resultCursor,
                    'ingestion_checkpoint' => $checkpoint?->toArray(),
                    'activation_status' => 'enabled',
                    'health_status' => $runExamined > 0 ? 'healthy' : 'idle',
                    'consecutive_failures' => 0,
                    'request_count' => $source->request_count + $requestCount,
                    'observation_count' => $source->observation_count + $runExamined,
                    'duplicate_observation_count' => $source->duplicate_observation_count + $runDuplicates,
                    'last_observation_at' => $runExamined > 0 ? now() : $source->last_observation_at,
                    'last_health_checked_at' => now(),
                    'last_provider_request_id' => $providerRequestId,
                    'last_retrieval_version' => $retrievalVersion,
                    'last_quota_remaining' => $rateLimit?->quotaRemaining,
                    'last_rate_limit_remaining' => $rateLimit?->remaining,
                    'last_retry_after_seconds' => $rateLimit?->retryAfterSeconds,
                    'next_eligible_ingestion_at' => $nextEligibleAt,
                    'last_ingestion_success_at' => now(),
                    'last_ingestion_failure_code' => null,
                    'last_ingestion_error' => null,
                ])->save();
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
