<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Queries;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeIngestionRun;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\EvaluateGiftCodeSourceActivationReadiness;
use Illuminate\Database\Eloquent\Collection;

final readonly class GiftCodeIngestionHealthQuery
{
    public function __construct(private EvaluateGiftCodeSourceActivationReadiness $readiness) {}

    /** @return list<array<string,mixed>> */
    public function get(int $limit = 50): array
    {
        /** @var Collection<int, GiftCodeSourceRegistry> $sources */
        $sources = GiftCodeSourceRegistry::query()
            ->with(['ingestionRuns' => static fn ($query) => $query->orderByDesc('started_at')->limit(5)])
            ->orderBy('source_key')
            ->limit(max(1, min(100, $limit)))
            ->get();
        $result = [];
        foreach ($sources as $source) {
            /** @var Collection<int, GiftCodeIngestionRun> $runs */
            $runs = $source->ingestionRuns;
            $runRows = [];
            foreach ($runs as $run) {
                $runRows[] = [
                    'id' => (string) $run->id,
                    'status' => $run->status,
                    'sourceCursor' => $run->source_cursor,
                    'resultCursor' => $run->result_cursor,
                    'checkpoint' => $run->result_checkpoint,
                    'requestCount' => $run->request_count,
                    'providerRequestId' => $run->provider_request_id,
                    'retrievalVersion' => $run->retrieval_version,
                    'quotaRemaining' => $run->quota_remaining,
                    'rateLimitRemaining' => $run->rate_limit_remaining,
                    'retryAfterSeconds' => $run->retry_after_seconds,
                    'examined' => $run->examined_count,
                    'accepted' => $run->accepted_count,
                    'duplicates' => $run->duplicate_count,
                    'quarantined' => $run->quarantined_count,
                    'failureCode' => $run->failure_code,
                    'failureMessage' => $run->failure_message,
                    'startedAt' => $run->started_at->toIso8601String(),
                    'completedAt' => $run->completed_at?->toIso8601String(),
                ];
            }
            $policy = $source->provenance_policy ?? [];
            $activation = $this->readiness->forSource($source)->toArray();
            $result[] = [
                'id' => (string) $source->id,
                'key' => $source->source_key,
                'name' => $source->name,
                'classification' => $source->classification,
                'canonicalDomain' => $source->canonical_domain,
                'adapterKey' => $source->adapter_key,
                'manualEvidenceAllowed' => ($policy['manual_evidence_allowed'] ?? false) === true,
                'active' => $source->is_active && $source->revoked_at === null,
                'ingestionEnabled' => $source->ingestion_enabled,
                'activationStatus' => $source->activation_status,
                'healthStatus' => $source->health_status,
                'activationReadiness' => $activation,
                'checkpoint' => $source->ingestion_checkpoint,
                'nextEligibleIngestionAt' => $source->next_eligible_ingestion_at?->toIso8601String(),
                'consecutiveFailures' => $source->consecutive_failures,
                'requestCount' => $source->request_count,
                'observationCount' => $source->observation_count,
                'duplicateObservationCount' => $source->duplicate_observation_count,
                'rateLimitEventCount' => $source->rate_limit_event_count,
                'lastObservationAt' => $source->last_observation_at?->toIso8601String(),
                'lastHealthCheckedAt' => $source->last_health_checked_at?->toIso8601String(),
                'lastProviderRequestId' => $source->last_provider_request_id,
                'lastRetrievalVersion' => $source->last_retrieval_version,
                'lastQuotaRemaining' => $source->last_quota_remaining,
                'lastRateLimitRemaining' => $source->last_rate_limit_remaining,
                'lastRetryAfterSeconds' => $source->last_retry_after_seconds,
                'lastAttemptAt' => $source->last_ingestion_attempt_at?->toIso8601String(),
                'lastSuccessAt' => $source->last_ingestion_success_at?->toIso8601String(),
                'lastFailureAt' => $source->last_ingestion_failure_at?->toIso8601String(),
                'failureCode' => $source->last_ingestion_failure_code,
                'error' => $source->last_ingestion_error,
                'stale' => $source->ingestion_enabled
                    && $source->next_eligible_ingestion_at === null
                    && ($source->last_ingestion_success_at === null || $source->last_ingestion_success_at->lt(now()->subDay())),
                'runs' => $runRows,
            ];
        }

        return $result;
    }
}
