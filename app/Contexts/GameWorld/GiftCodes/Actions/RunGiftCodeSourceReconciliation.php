<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSourceSyncMode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeIngestionRun;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceDelivery;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSubscription;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeProviderItemIdentity;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceHeadAcquirer;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceHealthProjector;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceSyncStateRepository;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class RunGiftCodeSourceReconciliation
{
    public function __construct(
        private GiftCodeSourceAdapterRegistry $adapters,
        private GiftCodeSourceHeadAcquirer $acquire,
        private IngestApprovedGiftCodeObservation $ingest,
        private GiftCodeSourceSyncStateRepository $syncStates,
        private GiftCodeSourceHealthProjector $health,
        private GiftCodeProviderItemIdentity $providerItems,
    ) {}

    /** @return array{sources:int,examined:int,accepted:int,duplicates:int,quarantined:int,gaps:int,failedSources:int} */
    public function handle(int $sourceLimit = 10, ?string $sourceKey = null): array
    {
        if (! (bool) config('game_world.gift_codes.approved_source_ingestion', false)) {
            return $this->result();
        }

        $sources = GiftCodeSourceRegistry::query()
            ->where('is_active', true)
            ->where('ingestion_enabled', true)
            ->where('reconciliation_enabled', true)
            ->whereNull('revoked_at')
            ->when($sourceKey !== null && $sourceKey !== '', static fn ($query) => $query->where('source_key', $sourceKey))
            ->orderBy('id')
            ->limit(max(1, min(100, $sourceLimit)))
            ->get();

        $totals = $this->result(sources: $sources->count());
        $observationLimit = max(1, min(500, (int) config('game_world.gift_codes.ingestion_batch_size', 100)));
        $maxPages = max(1, min(10, (int) config('game_world.gift_codes.ingestion_max_pages_per_source', 3)));

        foreach ($sources as $source) {
            $state = $this->syncStates->get($source, GiftCodeSourceSyncMode::Reconciliation);
            $run = GiftCodeIngestionRun::query()->create([
                'gift_code_source_id' => (string) $source->id,
                'status' => 'running',
                'sync_mode' => GiftCodeSourceSyncMode::Reconciliation->value,
                'source_cursor' => $state->active_page_token ?? $state->committed_high_water,
                'started_at' => now(),
            ]);

            try {
                $adapter = $this->adapters->find($source->adapter_key);
                if ($adapter === null) {
                    throw new \RuntimeException('No registered adapter matches this approved source.');
                }

                $batch = $this->acquire->handle($source, $adapter, $state, $observationLimit, $maxPages);
                $accepted = 0;
                $duplicates = 0;
                $quarantined = 0;
                $gaps = 0;
                $failureCode = null;
                $failureMessage = null;

                foreach ($batch->observations as $position => $observation) {
                    if ($this->isMissedPushDelivery($source, $observation)) {
                        $gaps++;
                    }
                    try {
                        $result = $this->ingest->handle((string) $source->id, $observation);
                        $accepted += $result['accepted'] ? 1 : 0;
                        $duplicates += $result['duplicate'] ? 1 : 0;
                        $quarantined += $result['quarantined'] ? 1 : 0;
                    } catch (Throwable $exception) {
                        report($exception);
                        $quarantined++;
                        $failureCode ??= $exception instanceof ValidationException
                            ? 'observation_policy_rejected'
                            : 'observation_ingestion_failed';
                        $failureMessage ??= mb_substr(sprintf(
                            'Reconciliation observation %d (%s): %s',
                            $position + 1,
                            $observation->code,
                            $exception->getMessage(),
                        ), 0, 2000);
                    }
                }

                $changes = $batch->syncStateChanges;
                unset($changes['last_head_poll_at']);
                $changes['last_reconciliation_at'] = now();
                $this->syncStates->advance($state, $changes);

                $run->forceFill([
                    'status' => $quarantined > 0 ? 'completed_with_quarantine' : 'completed',
                    'result_cursor' => $batch->resultCursor,
                    'result_checkpoint' => $batch->checkpoint?->toArray(),
                    'request_count' => $batch->requestCount,
                    'provider_request_id' => $batch->providerRequestId,
                    'retrieval_version' => $batch->retrievalVersion,
                    'quota_remaining' => $batch->rateLimit?->quotaRemaining,
                    'rate_limit_remaining' => $batch->rateLimit?->remaining,
                    'retry_after_seconds' => $batch->rateLimit?->retryAfterSeconds,
                    'examined_count' => count($batch->observations),
                    'accepted_count' => $accepted,
                    'duplicate_count' => $duplicates,
                    'quarantined_count' => $quarantined,
                    'failure_code' => $failureCode,
                    'failure_message' => $failureMessage,
                    'completed_at' => now(),
                ])->save();

                $source->forceFill([
                    'request_count' => $source->request_count + $batch->requestCount,
                    'observation_count' => $source->observation_count + count($batch->observations),
                    'last_provider_request_id' => $batch->providerRequestId,
                    'last_retrieval_version' => $batch->retrievalVersion,
                    'last_health_checked_at' => now(),
                    'reconciliation_gap_count' => $source->reconciliation_gap_count + $gaps,
                    'last_reconciliation_gap_at' => $gaps > 0 ? now() : $source->last_reconciliation_gap_at,
                ])->save();
                $this->health->recordCompletedRun(
                    $source,
                    count($batch->observations),
                    $accepted,
                    $duplicates,
                    $quarantined,
                );

                $totals['examined'] += count($batch->observations);
                $totals['accepted'] += $accepted;
                $totals['duplicates'] += $duplicates;
                $totals['quarantined'] += $quarantined;
                $totals['gaps'] += $gaps;
            } catch (Throwable $exception) {
                report($exception);
                $run->forceFill([
                    'status' => 'failed',
                    'failure_code' => 'reconciliation_failed',
                    'failure_message' => mb_substr($exception->getMessage(), 0, 2000),
                    'completed_at' => now(),
                ])->save();
                $source->forceFill([
                    'health_status' => 'degraded',
                    'consecutive_failures' => $source->consecutive_failures + 1,
                    'last_ingestion_failure_at' => now(),
                    'last_ingestion_failure_code' => 'reconciliation_failed',
                    'last_ingestion_error' => mb_substr($exception->getMessage(), 0, 2000),
                    'last_health_checked_at' => now(),
                ])->save();
                $totals['failedSources']++;
            }
        }

        return $totals;
    }

    private function isMissedPushDelivery(
        GiftCodeSourceRegistry $source,
        GiftCodeIngestionObservation $observation,
    ): bool {
        if (! $source->push_enabled) {
            return false;
        }
        $identity = $this->providerItems->fromObservation($observation);
        if ($identity === null || ! in_array($identity['provider'], ['youtube', 'facebook', 'discord', 'x'], true)) {
            return false;
        }

        $subscription = GiftCodeSourceSubscription::query()
            ->where('gift_code_source_id', $source->id)
            ->where('provider', $identity['provider'])
            ->where('status', 'active')
            ->orderByDesc('activated_at')
            ->first();
        if (! $subscription instanceof GiftCodeSourceSubscription || $subscription->activated_at === null) {
            return false;
        }

        if ($observation->publishedAt !== null) {
            try {
                if (CarbonImmutable::parse($observation->publishedAt)->lt($subscription->activated_at)) {
                    return false;
                }
            } catch (Throwable) {
                return false;
            }
        }

        return ! GiftCodeSourceDelivery::query()
            ->where('gift_code_source_id', $source->id)
            ->where('provider', $identity['provider'])
            ->where('provider_item_id', $identity['item_id'])
            ->exists();
    }

    /** @return array{sources:int,examined:int,accepted:int,duplicates:int,quarantined:int,gaps:int,failedSources:int} */
    private function result(
        int $sources = 0,
        int $examined = 0,
        int $accepted = 0,
        int $duplicates = 0,
        int $quarantined = 0,
        int $gaps = 0,
        int $failedSources = 0,
    ): array {
        return compact('sources', 'examined', 'accepted', 'duplicates', 'quarantined', 'gaps', 'failedSources');
    }
}
