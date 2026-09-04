<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\ValueObjects;

final readonly class ResolvedDeliveryPlan
{
    /** @param list<ResolvedDeliveryRoute> $routes */
    public function __construct(public array $routes) {}

    /** @return list<string> */
    public function channels(): array
    {
        return array_values(array_unique(array_map(
            static fn (ResolvedDeliveryRoute $route): string => $route->channel->value,
            $this->routes,
        )));
    }
}
