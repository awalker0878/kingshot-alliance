<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final readonly class GiftCodeSourceAcquisitionMode
{
    public function __construct(
        public bool $pushEnabled,
        public bool $headPollEnabled,
        public bool $reconciliationEnabled,
        public bool $backfillEnabled,
    ) {}
}
