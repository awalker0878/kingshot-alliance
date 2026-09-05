<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Contracts;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;

interface GiftCodeCanonicalFetcher
{
    /** @return array<string,mixed> */
    public function fetch(GiftCodeSourceRegistry $source, string $providerItemId): array;
}
