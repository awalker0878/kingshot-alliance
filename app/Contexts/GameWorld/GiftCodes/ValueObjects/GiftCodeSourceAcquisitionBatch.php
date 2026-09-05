<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

final readonly class GiftCodeSourceAcquisitionBatch
{
    /**
     * @param list<GiftCodeIngestionObservation> $observations
     * @param array<string,mixed> $syncStateChanges
     */
    public function __construct(
        public array $observations,
        public ?string $sourceCursor,
        public ?string $resultCursor,
        public ?GiftCodeSourceCheckpoint $checkpoint,
        public int $requestCount,
        public ?string $providerRequestId,
        public ?string $retrievalVersion,
        public ?GiftCodeSourceRateLimit $rateLimit,
        public array $syncStateChanges,
    ) {}
}
