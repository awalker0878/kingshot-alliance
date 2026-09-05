<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodePushProviderRegistry
{
    /** @return list<GiftCodePushProvider> */
    public function all(): array
    {
        return [
            new GiftCodePushProvider('youtube', 'websub', 'kingshot-youtube'),
            new GiftCodePushProvider('discord', 'gateway', 'kingshot-official-discord'),
            new GiftCodePushProvider('facebook', 'webhook', 'kingshot-facebook'),
            new GiftCodePushProvider('x', 'filtered-stream', 'kingshot-official-x'),
        ];
    }
}
