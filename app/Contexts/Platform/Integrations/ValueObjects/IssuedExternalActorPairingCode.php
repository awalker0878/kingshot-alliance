<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\ValueObjects;

final readonly class IssuedExternalActorPairingCode
{
    public function __construct(
        public string $pairingCodeId,
        public string $provider,
        public string $code,
        public string $expiresAt,
    ) {}
}
