<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final readonly class GiftCodePushProvider
{
    public function __construct(
        public string $provider,
        public string $transport,
        public string $sourceKey,
    ) {}
}
