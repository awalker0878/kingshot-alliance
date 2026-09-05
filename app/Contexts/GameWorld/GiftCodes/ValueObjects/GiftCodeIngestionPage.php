<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

final readonly class GiftCodeIngestionPage
{
    /** @param list<GiftCodeIngestionObservation> $observations */
    public function __construct(
        public array $observations,
        public ?string $nextCursor,
        public ?string $retrievalVersion = null,
        public ?string $providerRequestId = null,
        public ?GiftCodeSourceRateLimit $rateLimit = null,
        public ?GiftCodeSourceCheckpoint $checkpoint = null,
        public int $requestCount = 1,
    ) {}
}
