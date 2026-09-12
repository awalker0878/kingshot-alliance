<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\ValueObjects;

/** Retained Communications facts, not Content preparation progress or a retry authorization. */
final readonly class AnnouncementDeliverySummary
{
    /**
     * @param  array<string, int>  $deliveryCounts
     * @param  list<string>  $failedDeliveryIds
     */
    public function __construct(
        public int $readCount,
        public array $deliveryCounts,
        public int $retryCandidateCount,
        public array $failedDeliveryIds,
    ) {}
}
