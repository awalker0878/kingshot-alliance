<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class DeliveryOutcome
{
    private function __construct(
        public bool $delivered,
        public bool $retryable,
        public ?string $error,
        public ?CarbonImmutable $retryAt,
    ) {}

    public static function delivered(): self
    {
        return new self(true, false, null, null);
    }

    public static function failed(string $error, bool $retryable, ?CarbonImmutable $retryAt = null): self
    {
        return new self(false, $retryable, $error, $retryAt);
    }
}
