<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Exceptions;

use RuntimeException;
use Throwable;

final class WebhookAttemptFailed extends RuntimeException
{
    public function __construct(
        public readonly string $deliveryId,
        public readonly string $attemptToken,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
