<?php

declare(strict_types=1);

namespace App\ReadModels\Support;

use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Privacy boundary for read-model runtime diagnostics.
 *
 * Callers may emit identifiers, aggregate counts, stable reason codes and
 * duration only. Presentation text and source payloads cannot cross this API.
 */
final class ReadModelTelemetry
{
    /**
     * @param  array<string,string|int|null>  $ids
     * @param  array<string,int>  $counts
     * @param  list<string>  $reasonCodes
     */
    public static function record(
        string $event,
        int $startedAt,
        array $ids = [],
        array $counts = [],
        array $reasonCodes = [],
    ): void {
        foreach (array_keys($ids) as $key) {
            if (! str_ends_with($key, '_id')) {
                throw new LogicException('Read-model telemetry identifier keys must end in _id.');
            }
        }
        foreach (array_keys($counts) as $key) {
            if (! str_ends_with($key, '_count')) {
                throw new LogicException('Read-model telemetry count keys must end in _count.');
            }
        }

        Log::debug($event, [
            ...$ids,
            ...$counts,
            'reason_codes' => array_values(array_unique(array_filter(
                $reasonCodes,
                static fn (string $code): bool => $code !== '',
            ))),
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
        ]);
    }
}
