<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Queries;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeIngestionRun;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\EvaluateGiftCodeSourceActivationReadiness;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceUsefulnessMetrics;
use Illuminate\Database\Eloquent\Collection;

final readonly class GiftCodeIngestionHealthQuery
{
    public function __construct(
        private EvaluateGiftCodeSourceActivationReadiness $readiness,
        private GiftCodeSourceUsefulnessMetrics $usefulness,
    ) {}

    /** @return list<array<string,mixed>> */
    public function get(int $limit = 50): array
    {
        /** @var Collection<int, GiftCodeSourceRegistry> $sources */
        $sources = GiftCodeSourceRegistry::query()
            ->with([
                'ingestionRuns' => static fn ($query) => $query->orderByDesc('started_at')->limit(5),
                'syncStates',
                'subscriptions',
                'latestSmokeCheck',
                'performanceProjection',
            ])
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
                    'syncMode' => $run->sync_mode,
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

            $syncStates = [];
            foreach ($source->syncStates as $state) {
                $syncStates[] = [
                    'mode' => $state->sync_mode,
                    'latestObservedProviderId' => $state->latest_observed_provider_id,
                    'committedHighWater' => $state->committed_high_water,
                    'candidateHighWater' => $state->candidate_high_water,
                    'activeSinceId' => $state->active_sync_since_id,
                    'activePageToken' => $state->active_page_token,
                    'backfillPageToken' => $state->backfill_page_token,
                    'backfillBoundaryProviderId' => $state->backfill_boundary_provider_id,
                    'etag' => $state->http_etag,
                    'lastModified' => $state->http_last_modified,
                    'lastNotModifiedAt' => $state->last_not_modified_at?->toIso8601String(),
                    'lastHeadPollAt' => $state->last_head_poll_at?->toIso8601String(),
                    'lastReconciliationAt' => $state->last_reconciliation_at?->toIso8601String(),
                    'lastBackfillAt' => $state->last_backfill_at?->toIso8601String(),
                    'version' => $state->version,
                ];
            }

            $subscriptions = [];
            foreach ($source->subscriptions as $subscription) {
                $subscriptions[] = [
                    'provider' => $subscription->provider,
                    'transport' => $subscription->transport,
                    'status' => $subscription->status,
                    'providerSubscriptionId' => $subscription->provider_subscription_id,
                    'activatedAt' => $subscription->activated_at?->toIso8601String(),
                    'expiresAt' => $subscription->expires_at?->toIso8601String(),
                    'lastVerifiedAt' => $subscription->last_verified_at?->toIso8601String(),
                    'lastEventReceivedAt' => $subscription->last_event_received_at?->toIso8601String(),
                    'lastErrorCode' => $subscription->last_error_code,
                ];
            }

            $policy = $source->provenance_policy ?? [];
            $activation = $this->readiness->forSource($source)->toArray();
            $ratios = $this->usefulness->forSource($source);
            $smoke = $source->latestSmokeCheck;
            $performance = $source->performanceProjection;
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
                'pushEnabled' => $source->push_enabled,
                'headPollEnabled' => $source->head_poll_enabled,
                'reconciliationEnabled' => $source->reconciliation_enabled,
                'backfillEnabled' => $source->backfill_enabled,
                'authorityPromotionEnabled' => $source->authority_promotion_enabled,
                'activationStatus' => $source->activation_status,
                'healthStatus' => $source->health_status,
                'activationReadiness' => $activation,
                'latestSmokeCheck' => $smoke === null ? null : [
                    'status' => $smoke->status,
                    'checkedAt' => $smoke->checked_at->toIso8601String(),
                    'durationMs' => $smoke->duration_ms,
                    'observationCount' => $smoke->observation_count,
                    'retrievalVersion' => $smoke->retrieval_version,
                    'providerRequestId' => $smoke->provider_request_id,
                    'pushStatus' => $smoke->push_status,
                    'failureCode' => $smoke->failure_code,
                    'failureMessage' => $smoke->failure_message,
                ],
                'performance' => $performance === null ? null : [
                    'observations' => $performance->observations,
                    'uniqueCodesDiscovered' => $performance->unique_codes_discovered,
                    'firstDiscoveries' => $performance->first_discoveries,
                    'qualifiedObservations' => $performance->qualified_observations,
                    'confirmedCorrect' => $performance->confirmed_correct,
                    'confirmedIncorrect' => $performance->confirmed_incorrect,
                    'conflictingObservations' => $performance->conflicting_observations,
                    'medianDiscoveryLatencySeconds' => $performance->median_discovery_latency_seconds,
                    'medianConfirmationLatencySeconds' => $performance->median_confirmation_latency_seconds,
                    'medianTimeToCodeSeconds' => $performance->median_time_to_code_seconds,
                    'p95TimeToCodeSeconds' => $performance->p95_time_to_code_seconds,
                    'usefulObservationRatio' => $performance->useful_observation_ratio,
                    'quarantineRatio' => $performance->quarantine_ratio,
                    'duplicateRatio' => $performance->duplicate_ratio,
                    'latencySampleCount' => $performance->latency_sample_count,
                    'lastProductiveObservationAt' => $performance->last_productive_observation_at?->toIso8601String(),
                    'derivedAt' => $performance->derived_at->toIso8601String(),
                ],
                'syncStates' => $syncStates,
                'subscriptions' => $subscriptions,
                'nextEligibleIngestionAt' => $source->next_eligible_ingestion_at?->toIso8601String(),
                'consecutiveFailures' => $source->consecutive_failures,
                'consecutiveQuarantinedRuns' => $source->consecutive_quarantined_runs,
                'requestCount' => $source->request_count,
                'observationCount' => $source->observation_count,
                'acceptedObservationCount' => $source->accepted_observation_count,
                'quarantinedObservationCount' => $source->quarantined_observation_count,
                'duplicateObservationCount' => $source->duplicate_observation_count,
                'acceptanceRatio' => $ratios['acceptance_ratio'],
                'quarantineRatio' => $ratios['quarantine_ratio'],
                'duplicateRatio' => $ratios['duplicate_ratio'],
                'rateLimitEventCount' => $source->rate_limit_event_count,
                'reconciliationGapCount' => $source->reconciliation_gap_count,
                'signatureFailureCount' => $source->signature_failure_count,
                'replayRejectionCount' => $source->replay_rejection_count,
                'lastObservationAt' => $source->last_observation_at?->toIso8601String(),
                'lastAcceptedObservationAt' => $source->last_accepted_observation_at?->toIso8601String(),
                'lastQuarantinedObservationAt' => $source->last_quarantined_observation_at?->toIso8601String(),
                'lastPushReceivedAt' => $source->last_push_received_at?->toIso8601String(),
                'lastProviderEventAt' => $source->last_provider_event_at?->toIso8601String(),
                'lastReconciliationGapAt' => $source->last_reconciliation_gap_at?->toIso8601String(),
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
