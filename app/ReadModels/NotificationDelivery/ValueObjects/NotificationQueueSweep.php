<?php

declare(strict_types=1);

namespace App\ReadModels\NotificationDelivery\ValueObjects;

final readonly class NotificationQueueSweep
{
    public function __construct(
        public int $examinedRecipients,
        public int $authorizedRecipients,
        public int $factCount,
        public int $deliveryCount,
        public int $createdDeliveryCount,
        public int $replayedDeliveryCount,
        public int $skippedRecipients,
        public ?string $nextCursor,
        public bool $truncated,
        public int $durationMs,
    ) {}

    /** @return array<string,bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'examinedRecipients' => $this->examinedRecipients,
            'authorizedRecipients' => $this->authorizedRecipients,
            'factCount' => $this->factCount,
            'deliveryCount' => $this->deliveryCount,
            'createdDeliveryCount' => $this->createdDeliveryCount,
            'replayedDeliveryCount' => $this->replayedDeliveryCount,
            'skippedRecipients' => $this->skippedRecipients,
            'nextCursor' => $this->nextCursor,
            'truncated' => $this->truncated,
            'durationMs' => $this->durationMs,
        ];
    }
}
