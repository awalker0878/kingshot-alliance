<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\ValueObjects;

final readonly class SpatialObservationRecordResult
{
    public function __construct(
        public string $receiptId,
        public string $observationId,
        public bool $idempotentReplay,
    ) {}
}
