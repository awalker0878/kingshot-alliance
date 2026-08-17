<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\ValueObjects;

final readonly class IssuedWebhookSubscription
{
    public function __construct(
        public string $subscriptionId,
        public string $name,
        public string $signingSecret,
    ) {}
}
