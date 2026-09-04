<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Enums;

enum EndpointHealthStatus: string
{
    case NeverTested = 'never_tested';
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Paused = 'paused';
}
