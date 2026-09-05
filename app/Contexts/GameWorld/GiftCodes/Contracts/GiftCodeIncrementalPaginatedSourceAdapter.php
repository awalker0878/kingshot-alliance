<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Contracts;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionPage;

interface GiftCodeIncrementalPaginatedSourceAdapter extends GiftCodeSourceAdapter
{
    public function acquireIncremental(
        GiftCodeSourceRegistry $source,
        ?string $sinceId,
        ?string $pageToken,
        int $limit,
    ): GiftCodeIngestionPage;
}
