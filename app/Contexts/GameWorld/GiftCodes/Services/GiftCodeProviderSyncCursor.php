<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final readonly class GiftCodeProviderSyncCursor
{
    public function __construct(
        public ?string $committedHighWater = null,
        public ?string $candidateHighWater = null,
        public ?string $activeSinceId = null,
        public ?string $pageToken = null,
    ) {}
}
