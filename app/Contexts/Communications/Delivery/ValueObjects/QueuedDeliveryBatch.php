<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\ValueObjects;

final readonly class QueuedDeliveryBatch
{
    /**
     * @param list<string> $deliveryIds
     * @param list<string> $channels
     */
    public function __construct(
        public array $deliveryIds,
        public array $channels,
    ) {}

    public function count(): int
    {
        return count($this->deliveryIds);
    }
}
