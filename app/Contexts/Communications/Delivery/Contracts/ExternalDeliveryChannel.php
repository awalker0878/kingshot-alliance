<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Contracts;

use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryAttempt;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryOutcome;

interface ExternalDeliveryChannel
{
    public function channel(): DeliveryChannel;

    /** @param array<string, string> $configuration */
    public function deliver(DeliveryAttempt $attempt, array $configuration): DeliveryOutcome;
}
