<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodePushDeliveryIdentity
{
    public function replayKey(string $provider, string $sourceKey, ?string $eventId, string $rawBody): string
    {
        return hash('sha256', $provider."\0".$sourceKey."\0".($eventId ?? '')."\0".hash('sha256', $rawBody));
    }
}
