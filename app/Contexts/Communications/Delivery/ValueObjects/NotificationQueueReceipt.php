<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\ValueObjects;

final readonly class NotificationQueueReceipt
{
    /**
     * @param list<string> $deliveryIds
     * @param list<string> $channels
     * @param list<string> $createdDeliveryIds
     */
    public function __construct(
        public string $messageId,
        public array $deliveryIds,
        public array $channels,
        public array $createdDeliveryIds,
        public bool $createdMessage,
        public ?string $inAppDeliveryId,
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
