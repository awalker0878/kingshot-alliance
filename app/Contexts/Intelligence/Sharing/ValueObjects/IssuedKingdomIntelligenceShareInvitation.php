<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\ValueObjects;

final readonly class IssuedKingdomIntelligenceShareInvitation
{
    public function __construct(
        public string $shareId,
        public string $token,
    ) {}
}
