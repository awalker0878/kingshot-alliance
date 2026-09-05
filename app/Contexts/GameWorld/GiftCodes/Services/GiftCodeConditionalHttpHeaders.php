<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSyncState;

final class GiftCodeConditionalHttpHeaders
{
    /** @return array<string,string> */
    public function forState(GiftCodeSourceSyncState $state): array
    {
        return array_filter([
            'If-None-Match' => $state->http_etag,
            'If-Modified-Since' => $state->http_last_modified,
        ], static fn (?string $value): bool => $value !== null && trim($value) !== '');
    }
}
