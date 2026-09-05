<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\ValueObjects;

use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use Carbon\CarbonImmutable;

final readonly class EffectiveRoutingPolicy
{
    /** @param array<string,mixed> $settings */
    public function __construct(
        public string $timezone,
        public bool $quietHoursEnabled,
        public ?string $quietHoursStart,
        public ?string $quietHoursEnd,
        public bool $allowUrgentDuringQuietHours,
        public ?CarbonImmutable $mutedUntil,
        public DigestCadence $digestCadence,
        public array $settings = [],
    ) {}
}
