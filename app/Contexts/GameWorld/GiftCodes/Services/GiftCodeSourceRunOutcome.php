<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final readonly class GiftCodeSourceRunOutcome
{
    public function __construct(
        public int $examined,
        public int $accepted,
        public int $duplicates,
        public int $quarantined,
    ) {}

    public function healthStatus(): string
    {
        if ($this->quarantined > 0) {
            return 'degraded';
        }

        return $this->examined > 0 ? 'healthy' : 'idle';
    }
}
