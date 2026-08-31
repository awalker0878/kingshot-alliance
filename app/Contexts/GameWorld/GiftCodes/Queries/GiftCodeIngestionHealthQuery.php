<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Queries;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeIngestionRun;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use Illuminate\Database\Eloquent\Collection;

final class GiftCodeIngestionHealthQuery
{
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
            $result[] = [
                'id' => (string) $source->id,
                'key' => $source->source_key,
                'name' => $source->name,
                'classification' => $source->classification,
                'canonicalDomain' => $source->canonical_domain,
                'adapterKey' => $source->adapter_key,
                'active' => $source->is_active && $source->revoked_at === null,
                'ingestionEnabled' => $source->ingestion_enabled,
                'lastAttemptAt' => $source->last_ingestion_attempt_at?->toIso8601String(),
                'lastSuccessAt' => $source->last_ingestion_success_at?->toIso8601String(),
                'lastFailureAt' => $source->last_ingestion_failure_at?->toIso8601String(),
                'failureCode' => $source->last_ingestion_failure_code,
                'error' => $source->last_ingestion_error,
                'stale' => $source->ingestion_enabled
                    && ($source->last_ingestion_success_at === null || $source->last_ingestion_success_at->lt(now()->subDay())),
                'runs' => $runRows,
            ];
        }

        return $result;
    }
}
