<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use Illuminate\Support\Facades\Cache;

final readonly class RebuildGiftCodeAcquisitionIntelligence
{
    private const CLUSTER_CURSOR = 'gift-codes:acquisition-intelligence:cluster-cursor';

    private const SOURCE_CURSOR = 'gift-codes:acquisition-intelligence:source-cursor';

    public function __construct(
        private RebuildGiftCodeObservationClusters $clusters,
        private RebuildGiftCodeSourcePerformance $sources,
    ) {
    }

    /** @return array{clusters:array{examined:int,updated:int,nextCursor:?string},sources:array{examined:int,updated:int,nextCursor:?string}} */
    public function cycle(int $clusterLimit = 200, int $sourceLimit = 100): array
    {
        $clusterCursor = Cache::get(self::CLUSTER_CURSOR);
        $sourceCursor = Cache::get(self::SOURCE_CURSOR);
        $clusterResult = $this->clusters->handle($clusterLimit, is_string($clusterCursor) ? $clusterCursor : null);
        $sourceResult = $this->sources->handle($sourceLimit, is_string($sourceCursor) ? $sourceCursor : null);

        $this->remember(self::CLUSTER_CURSOR, $clusterResult['nextCursor']);
        $this->remember(self::SOURCE_CURSOR, $sourceResult['nextCursor']);

        return ['clusters' => $clusterResult, 'sources' => $sourceResult];
    }

    private function remember(string $key, ?string $cursor): void
    {
        if ($cursor === null) {
            Cache::forget($key);

            return;
        }

        Cache::forever($key, $cursor);
    }
}
