<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\ValueObjects;

/** Provider truth and the endpoint generation actually observed for IO; contains no credentials. */
final readonly class AttemptTransportResult
{
    public function __construct(
        public DeliveryOutcome $outcome,
        public ?int $endpointVerificationGeneration,
    ) {}
}
