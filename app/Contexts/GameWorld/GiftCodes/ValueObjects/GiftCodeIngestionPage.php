<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

final readonly class GiftCodeIngestionPage
{
    /** @param list<GiftCodeIngestionObservation> $observations */
    public function __construct(
        public array $observations,
        public ?string $nextCursor,
    ) {}
}
