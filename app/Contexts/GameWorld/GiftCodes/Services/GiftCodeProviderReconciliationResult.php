<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final readonly class GiftCodeProviderReconciliationResult
{
    public function __construct(
        public int $examined,
        public int $recovered,
        public int $duplicates,
        public int $gaps,
    ) {}
}
