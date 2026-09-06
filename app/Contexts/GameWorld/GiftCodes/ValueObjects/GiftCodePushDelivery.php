<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

final readonly class GiftCodePushDelivery
{
    public function __construct(
        public string $provider,
        public string $sourceKey,
        public ?string $providerEventId,
        public ?string $providerItemId,
        public string $replayKey,
        public string $payloadSha256,
        public ?string $correlationId = null,
    ) {}
}
