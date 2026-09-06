<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeIncrementalPaginatedSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSyncState;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionPage;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeSourceAcquisitionBatch;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeSourceCheckpoint;
use UnexpectedValueException;

final readonly class GiftCodeSourceHeadAcquirer
{
    public function __construct(
        private GiftCodeSourcePaginationPolicy $pagination,
        private GiftCodeProviderHighWater $highWater,
    ) {}

    public function handle(
        GiftCodeSourceRegistry $source,
        GiftCodeSourceAdapter $adapter,
        GiftCodeSourceSyncState $state,
        int $observationLimit,
        int $maxPages,
    ): GiftCodeSourceAcquisitionBatch {
        $observationLimit = max(1, min(500, $observationLimit));
        $maxPages = max(1, min(10, $maxPages));

        if ($adapter instanceof GiftCodeIncrementalPaginatedSourceAdapter) {
            return $this->incremental($source, $adapter, $state, $observationLimit, $maxPages);
        }

        if ($this->pagination->isOpaqueHeadPaged($source->adapter_key)) {
            return $this->opaqueHead($source, $adapter, $state, $observationLimit, $maxPages);
        }

        return $this->generic($source, $adapter, $state, $observationLimit, $maxPages);
    }

    private function incremental(
        GiftCodeSourceRegistry $source,
        GiftCodeIncrementalPaginatedSourceAdapter $adapter,
        GiftCodeSourceSyncState $state,
        int $observationLimit,
        int $maxPages,
    ): GiftCodeSourceAcquisitionBatch {
        $sinceId = $state->active_sync_since_id ?? $state->committed_high_water;
        $pageToken = $state->active_page_token;
        $candidateHighWater = $state->candidate_high_water;
        $observations = [];
        $requestCount = 0;
        $providerRequestId = null;
        $retrievalVersion = null;
        $rateLimit = null;
        $checkpoint = null;
        $nextToken = $pageToken;
        $seenTokens = [];

        for ($pageNumber = 0; $pageNumber < $maxPages && count($observations) < $observationLimit; $pageNumber++) {
            if ($nextToken !== null && isset($seenTokens[$nextToken])) {
                throw new UnexpectedValueException('The incremental source adapter repeated a provider pagination token.');
            }
            if ($nextToken !== null) {
                $seenTokens[$nextToken] = true;
            }

            $remaining = max(1, $observationLimit - count($observations));
            $page = $adapter->acquireIncremental($source, $sinceId, $nextToken, $remaining);
            $this->assertBounded($page, $remaining);
            $observations = [...$observations, ...$page->observations];
            $requestCount += max(1, $page->requestCount);
            $providerRequestId = $page->providerRequestId ?? $providerRequestId;
            $retrievalVersion = $page->retrievalVersion ?? $retrievalVersion;
            $rateLimit = $page->rateLimit ?? $rateLimit;
            $checkpoint = $page->checkpoint ?? $checkpoint;

            $pageCandidate = $checkpoint?->providerState['candidate_high_water'] ?? null;
            if (is_string($pageCandidate) && trim($pageCandidate) !== '') {
                $candidateHighWater = $this->highWater->greaterNumericId($candidateHighWater, trim($pageCandidate));
            }

            $nextToken = $page->nextCursor;
            if ($nextToken === null) {
                break;
            }
        }

        $completed = $nextToken === null;
        $changes = [
            'latest_observed_provider_id' => $candidateHighWater ?? $state->latest_observed_provider_id,
            'last_head_poll_at' => now(),
        ];
        if ($completed) {
            $changes = [
                ...$changes,
                'committed_high_water' => $candidateHighWater ?? $state->committed_high_water,
                'candidate_high_water' => null,
                'active_sync_since_id' => null,
                'active_page_token' => null,
            ];
        } else {
            $changes = [
                ...$changes,
                'candidate_high_water' => $candidateHighWater,
                'active_sync_since_id' => $sinceId,
                'active_page_token' => $nextToken,
            ];
        }

        return new GiftCodeSourceAcquisitionBatch(
            observations: $observations,
            sourceCursor: $pageToken ?? $sinceId,
            resultCursor: $nextToken ?? ($completed ? ($candidateHighWater ?? $sinceId) : null),
            checkpoint: $checkpoint,
            requestCount: $requestCount,
            providerRequestId: $providerRequestId,
            retrievalVersion: $retrievalVersion,
            rateLimit: $rateLimit,
            syncStateChanges: $this->withHttpState($changes, $checkpoint, $state),
        );
    }

    private function opaqueHead(
        GiftCodeSourceRegistry $source,
        GiftCodeSourceAdapter $adapter,
        GiftCodeSourceSyncState $state,
        int $observationLimit,
        int $maxPages,
    ): GiftCodeSourceAcquisitionBatch {
        $head = $adapter->acquire($source, null, $observationLimit);
        $this->assertBounded($head, $observationLimit);

        $observations = $head->observations;
        $requestCount = max(1, $head->requestCount);
        $providerRequestId = $head->providerRequestId;
        $retrievalVersion = $head->retrievalVersion;
        $rateLimit = $head->rateLimit;
        $checkpoint = $head->checkpoint;
        $headCandidate = $this->pagination->latestProviderId($head->checkpoint);
        $candidateHighWater = $headCandidate ?? $state->candidate_high_water ?? $state->latest_observed_provider_id;
        $previousBoundary = $state->committed_high_water;
        $headIds = $this->pagination->providerItemIds($head->checkpoint);
        $boundaryReached = $previousBoundary === null
            || in_array($previousBoundary, $headIds, true)
            || $head->nextCursor === null;
        $nextToken = null;

        if (! $boundaryReached) {
            $nextToken = $state->active_page_token ?? $head->nextCursor;
            $seenTokens = [];
            for ($pageNumber = 1; $pageNumber < $maxPages && count($observations) < $observationLimit; $pageNumber++) {
                if ($nextToken === null) {
                    $boundaryReached = true;
                    break;
                }
                if (isset($seenTokens[$nextToken])) {
                    throw new UnexpectedValueException('The opaque source adapter repeated a provider pagination token.');
                }
                $seenTokens[$nextToken] = true;

                $remaining = max(1, $observationLimit - count($observations));
                $page = $adapter->acquire($source, $nextToken, $remaining);
                $this->assertBounded($page, $remaining);
                $observations = [...$observations, ...$page->observations];
                $requestCount += max(1, $page->requestCount);
                $providerRequestId = $page->providerRequestId ?? $providerRequestId;
                $retrievalVersion = $page->retrievalVersion ?? $retrievalVersion;
                $rateLimit = $page->rateLimit ?? $rateLimit;
                $checkpoint = $page->checkpoint ?? $checkpoint;

                $pageIds = $this->pagination->providerItemIds($page->checkpoint);
                if ($previousBoundary !== null && in_array($previousBoundary, $pageIds, true)) {
                    $boundaryReached = true;
                    $nextToken = null;
                    break;
                }
                $nextToken = $page->nextCursor;
                if ($nextToken === null) {
                    $boundaryReached = true;
                    break;
                }
            }
        }

        $changes = [
            'latest_observed_provider_id' => $headCandidate ?? $state->latest_observed_provider_id,
            'candidate_high_water' => $boundaryReached ? null : $candidateHighWater,
            'active_sync_since_id' => $boundaryReached ? null : $previousBoundary,
            'active_page_token' => $boundaryReached ? null : $nextToken,
            'last_head_poll_at' => now(),
        ];
        if ($boundaryReached && $candidateHighWater !== null) {
            $changes['committed_high_water'] = $candidateHighWater;
        }

        return new GiftCodeSourceAcquisitionBatch(
            observations: $observations,
            sourceCursor: $state->active_page_token ?? $previousBoundary,
            resultCursor: $boundaryReached ? $candidateHighWater : $nextToken,
            checkpoint: $checkpoint,
            requestCount: $requestCount,
            providerRequestId: $providerRequestId,
            retrievalVersion: $retrievalVersion,
            rateLimit: $rateLimit,
            syncStateChanges: $this->withHttpState($changes, $head->checkpoint ?? $checkpoint, $state),
        );
    }

    private function generic(
        GiftCodeSourceRegistry $source,
        GiftCodeSourceAdapter $adapter,
        GiftCodeSourceSyncState $state,
        int $observationLimit,
        int $maxPages,
    ): GiftCodeSourceAcquisitionBatch {
        $cursor = $state->active_page_token ?? $state->committed_high_water;
        $sourceCursor = $cursor;
        $observations = [];
        $requestCount = 0;
        $providerRequestId = null;
        $retrievalVersion = null;
        $rateLimit = null;
        $checkpoint = null;
        $resultCursor = $cursor;
        $seenCursors = [];

        for ($pageNumber = 0; $pageNumber < $maxPages && count($observations) < $observationLimit; $pageNumber++) {
            if ($cursor !== null && isset($seenCursors[$cursor])) {
                throw new UnexpectedValueException('The source adapter repeated an ingestion cursor.');
            }
            if ($cursor !== null) {
                $seenCursors[$cursor] = true;
            }

            $remaining = max(1, $observationLimit - count($observations));
            $page = $adapter->acquire($source, $cursor, $remaining);
            $this->assertBounded($page, $remaining);
            $observations = [...$observations, ...$page->observations];
            $requestCount += max(1, $page->requestCount);
            $providerRequestId = $page->providerRequestId ?? $providerRequestId;
            $retrievalVersion = $page->retrievalVersion ?? $retrievalVersion;
            $rateLimit = $page->rateLimit ?? $rateLimit;
            $checkpoint = $page->checkpoint ?? $checkpoint;
            $resultCursor = $page->nextCursor;

            if ($page->nextCursor === null || $page->nextCursor === $cursor) {
                break;
            }
            $cursor = $page->nextCursor;
        }

        $changes = [
            'committed_high_water' => $resultCursor,
            'active_page_token' => null,
            'candidate_high_water' => null,
            'active_sync_since_id' => null,
            'latest_observed_provider_id' => $this->pagination->latestProviderId($checkpoint) ?? $state->latest_observed_provider_id,
            'last_head_poll_at' => now(),
        ];

        return new GiftCodeSourceAcquisitionBatch(
            observations: $observations,
            sourceCursor: $sourceCursor,
            resultCursor: $resultCursor,
            checkpoint: $checkpoint,
            requestCount: $requestCount,
            providerRequestId: $providerRequestId,
            retrievalVersion: $retrievalVersion,
            rateLimit: $rateLimit,
            syncStateChanges: $this->withHttpState($changes, $checkpoint, $state),
        );
    }

    private function assertBounded(GiftCodeIngestionPage $page, int $remaining): void
    {
        if (count($page->observations) > $remaining) {
            throw new UnexpectedValueException('The source adapter exceeded the bounded observation limit.');
        }
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function withHttpState(
        array $changes,
        ?GiftCodeSourceCheckpoint $checkpoint,
        GiftCodeSourceSyncState $state,
    ): array {
        $providerState = $checkpoint === null ? [] : $checkpoint->providerState;
        $etag = $providerState['http_etag'] ?? null;
        $lastModified = $providerState['http_last_modified'] ?? null;
        $notModified = ($providerState['not_modified'] ?? false) === true;

        if (is_string($etag) && trim($etag) !== '') {
            $changes['http_etag'] = trim($etag);
        } elseif (! array_key_exists('http_etag', $changes)) {
            $changes['http_etag'] = $state->http_etag;
        }
        if (is_string($lastModified) && trim($lastModified) !== '') {
            $changes['http_last_modified'] = trim($lastModified);
        } elseif (! array_key_exists('http_last_modified', $changes)) {
            $changes['http_last_modified'] = $state->http_last_modified;
        }
        if ($notModified) {
            $changes['last_not_modified_at'] = now();
        }

        return $changes;
    }
}
