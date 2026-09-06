<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSourceSyncMode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeIngestionRun;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceHealthProjector;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourcePaginationPolicy;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceSyncStateRepository;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class RunGiftCodeSourceBackfill
{
    public function __construct(
        private GiftCodeSourceAdapterRegistry $adapters,
        private GiftCodeSourceSyncStateRepository $syncStates,
        private GiftCodeSourcePaginationPolicy $pagination,
        private GiftCodeSourceHealthProjector $health,
        private IngestApprovedGiftCodeObservation $ingest,
    ) {}

    /** @return array{sources:int,examined:int,accepted:int,duplicates:int,quarantined:int,completed:int,failedSources:int} */
    public function handle(int $sourceLimit = 5, ?string $sourceKey = null, bool $restart = false): array
    {
        if (! (bool) config('game_world.gift_codes.approved_source_ingestion', false)) {
            return $this->result();
        }

        $sources = GiftCodeSourceRegistry::query()
            ->where('is_active', true)
            ->where('ingestion_enabled', true)
            ->where('backfill_enabled', true)
            ->whereNull('revoked_at')
            ->when($sourceKey !== null && $sourceKey !== '', static fn ($query) => $query->where('source_key', $sourceKey))
            ->orderBy('id')
            ->limit(max(1, min(50, $sourceLimit)))
            ->get()
            ->filter(fn (GiftCodeSourceRegistry $source): bool => $this->pagination->isOpaqueHeadPaged($source->adapter_key))
            ->values();

        $totals = $this->result(sources: $sources->count());
        $observationLimit = max(1, min(500, (int) config('game_world.gift_codes.ingestion_batch_size', 100)));
        $maxPages = max(1, min(10, (int) config('game_world.gift_codes.ingestion_max_pages_per_source', 3)));

        foreach ($sources as $source) {
            $state = $this->syncStates->get($source, GiftCodeSourceSyncMode::Backfill);
            if ($restart) {
                $this->syncStates->advance($state, [
                    'backfill_page_token' => null,
                    'backfill_boundary_provider_id' => null,
                    'last_backfill_at' => null,
                ]);
                $state->refresh();
            } elseif ($state->last_backfill_at !== null && $state->backfill_page_token === null) {
                $totals['completed']++;
                continue;
            }

            $run = GiftCodeIngestionRun::query()->create([
                'gift_code_source_id' => (string) $source->id,
                'status' => 'running',
                'sync_mode' => GiftCodeSourceSyncMode::Backfill->value,
                'source_cursor' => $state->backfill_page_token,
                'started_at' => now(),
            ]);

            try {
                $adapter = $this->adapters->find($source->adapter_key);
                if ($adapter === null) {
                    throw new \RuntimeException('No registered adapter matches this approved source.');
                }

                $cursor = $state->backfill_page_token;
                $boundary = $state->backfill_boundary_provider_id;
                $observations = [];
                $requestCount = 0;
                $providerRequestId = null;
                $retrievalVersion = null;
                $rateLimit = null;
                $checkpoint = null;
                $seen = [];

                for ($pageNumber = 0; $pageNumber < $maxPages && count($observations) < $observationLimit; $pageNumber++) {
                    if ($cursor !== null && isset($seen[$cursor])) {
                        throw new \UnexpectedValueException('The source adapter repeated a historical backfill page token.');
                    }
                    if ($cursor !== null) {
                        $seen[$cursor] = true;
                    }

                    $remaining = max(1, $observationLimit - count($observations));
                    $page = $adapter->acquire($source, $cursor, $remaining);
                    if (count($page->observations) > $remaining) {
                        throw new \UnexpectedValueException('The source adapter exceeded the bounded historical backfill observation limit.');
                    }
                    $observations = [...$observations, ...$page->observations];
                    $requestCount += max(1, $page->requestCount);
                    $providerRequestId = $page->providerRequestId ?? $providerRequestId;
                    $retrievalVersion = $page->retrievalVersion ?? $retrievalVersion;
                    $rateLimit = $page->rateLimit ?? $rateLimit;
                    $checkpoint = $page->checkpoint ?? $checkpoint;
                    $boundary ??= $this->pagination->latestProviderId($page->checkpoint);
                    $cursor = $page->nextCursor;
                    if ($cursor === null) {
                        break;
                    }
                }

                $accepted = 0;
                $duplicates = 0;
                $quarantined = 0;
                $failureCode = null;
                $failureMessage = null;
                foreach ($observations as $position => $observation) {
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
                            'Backfill observation %d (%s): %s',
                            $position + 1,
                            $observation->code,
                            $exception->getMessage(),
                        ), 0, 2000);
                    }
                }

                $this->syncStates->advance($state, [
                    'backfill_page_token' => $cursor,
                    'backfill_boundary_provider_id' => $boundary,
                    'last_backfill_at' => now(),
                ]);

                $run->forceFill([
                    'status' => $quarantined > 0 ? 'completed_with_quarantine' : 'completed',
                    'result_cursor' => $cursor,
                    'result_checkpoint' => $checkpoint?->toArray(),
                    'request_count' => $requestCount,
                    'provider_request_id' => $providerRequestId,
                    'retrieval_version' => $retrievalVersion,
                    'quota_remaining' => $rateLimit?->quotaRemaining,
                    'rate_limit_remaining' => $rateLimit?->remaining,
                    'retry_after_seconds' => $rateLimit?->retryAfterSeconds,
                    'examined_count' => count($observations),
                    'accepted_count' => $accepted,
                    'duplicate_count' => $duplicates,
                    'quarantined_count' => $quarantined,
                    'failure_code' => $failureCode,
                    'failure_message' => $failureMessage,
                    'completed_at' => now(),
                ])->save();

                $source->forceFill([
                    'request_count' => $source->request_count + $requestCount,
                    'observation_count' => $source->observation_count + count($observations),
                    'last_provider_request_id' => $providerRequestId,
                    'last_retrieval_version' => $retrievalVersion,
                    'last_health_checked_at' => now(),
                ])->save();
                $this->health->recordCompletedRun($source, count($observations), $accepted, $duplicates, $quarantined);

                $totals['examined'] += count($observations);
                $totals['accepted'] += $accepted;
                $totals['duplicates'] += $duplicates;
                $totals['quarantined'] += $quarantined;
                if ($cursor === null) {
                    $totals['completed']++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $run->forceFill([
                    'status' => 'failed',
                    'failure_code' => 'backfill_failed',
                    'failure_message' => mb_substr($exception->getMessage(), 0, 2000),
                    'completed_at' => now(),
                ])->save();
                $source->forceFill([
                    'health_status' => 'degraded',
                    'consecutive_failures' => $source->consecutive_failures + 1,
                    'last_ingestion_failure_at' => now(),
                    'last_ingestion_failure_code' => 'backfill_failed',
                    'last_ingestion_error' => mb_substr($exception->getMessage(), 0, 2000),
                    'last_health_checked_at' => now(),
                ])->save();
                $totals['failedSources']++;
            }
        }

        return $totals;
    }

    /** @return array{sources:int,examined:int,accepted:int,duplicates:int,quarantined:int,completed:int,failedSources:int} */
    private function result(
        int $sources = 0,
        int $examined = 0,
        int $accepted = 0,
        int $duplicates = 0,
        int $quarantined = 0,
        int $completed = 0,
        int $failedSources = 0,
    ): array {
        return compact('sources', 'examined', 'accepted', 'duplicates', 'quarantined', 'completed', 'failedSources');
    }
}
