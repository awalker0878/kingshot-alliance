<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Exceptions;

use RuntimeException;

final class GiftCodeSourceAcquisitionException extends RuntimeException
{
    public function __construct(
        public readonly string $failureCode,
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?string $providerRequestId = null,
    ) {
        parent::__construct($message);
    }
}
