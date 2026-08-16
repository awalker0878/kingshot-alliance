<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\ValueObjects;

final readonly class KingdomReference
{
    public function __construct(
        public string $kingdomId,
        public int $number,
        public string $status,
    ) {}
}
