<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final readonly class GiftCodeSourceDeliveryOutcome
{
    public function __construct(
        public string $status,
        public int $observations,
        public int $accepted,
        public int $quarantined,
    ) {}
}
