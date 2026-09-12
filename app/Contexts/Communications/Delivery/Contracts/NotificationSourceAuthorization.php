<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Contracts;

use App\Contexts\Communications\Delivery\ValueObjects\NotificationSource;

/** Source owners decide access; Communications owns destination policy and dispatch. */
interface NotificationSourceAuthorization
{
    public function allows(NotificationSource $source): bool;
}
