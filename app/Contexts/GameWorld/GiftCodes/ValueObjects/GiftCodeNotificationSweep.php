<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

final readonly class GiftCodeNotificationSweep
{
    public function __construct(
        public int $examined,
        public int $eligible,
        public int $deliveryCount,
        public int $createdDeliveryCount,
        public int $skipped,
        public ?string $nextCursor,
        public bool $truncated,
        public int $durationMs,
    ) {}

    /** @return array<string,int|string|bool|null> */
    public function toArray(): array
    {
        return [
            'examined' => $this->examined,
            'eligible' => $this->eligible,
            'deliveryCount' => $this->deliveryCount,
            'createdDeliveryCount' => $this->createdDeliveryCount,
            'replayedDeliveryCount' => max(0, $this->deliveryCount - $this->createdDeliveryCount),
            'skipped' => $this->skipped,
            'nextCursor' => $this->nextCursor,
            'truncated' => $this->truncated,
            'durationMs' => $this->durationMs,
        ];
    }
}
