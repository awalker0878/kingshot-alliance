<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Queries;

use App\Contexts\Alliance\Content\Models\MediaAsset;

final class ContentStorageUsageQuery
{
    public function bytes(string $allianceId): int
    {
        return (int) MediaAsset::query()
            ->where('alliance_id', $allianceId)
            ->sum('size_bytes');
    }
}
