<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\ValueObjects;

use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use Carbon\CarbonImmutable;

final readonly class ResolvedDeliveryRoute
{
    public function __construct(
        public DeliveryChannel $channel,
        public ?string $endpointId,
        public ?string $targetLabel,
        public CarbonImmutable $dueAt,
        public string $reason,
        public DigestCadence $digestCadence = DigestCadence::Immediate,
    ) {}
}
