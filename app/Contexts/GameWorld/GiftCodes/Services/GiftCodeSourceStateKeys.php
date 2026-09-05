<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeSourceStateKeys
{
    public static function replayKey(string $provider, string $sourceId, ?string $providerEventId, string $payloadSha256): string
    {
        return hash('sha256', implode('|', [
            $provider,
            $sourceId,
            $providerEventId ?? '',
            $payloadSha256,
        ]));
    }
}
