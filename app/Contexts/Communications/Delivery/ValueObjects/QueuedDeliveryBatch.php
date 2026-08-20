<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\ValueObjects;

final readonly class QueuedDeliveryBatch
{
    /**
     * @param  list<string>  $deliveryIds
     * @param  list<string>  $channels
     * @param  list<string>  $createdDeliveryIds
     */
    public function __construct(
        public array $deliveryIds,
        public array $channels,
        public array $createdDeliveryIds,
    ) {}

    public function count(): int
    {
        return count($this->deliveryIds);
    }

    public function hasCreatedDeliveries(): bool
    {
        return $this->createdDeliveryIds !== [];
    }
}
