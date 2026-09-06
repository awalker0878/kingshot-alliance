<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;

final class GiftCodeProviderItemIdentity
{
    /** @return array{provider:string,item_id:string}|null */
    public function fromObservation(GiftCodeIngestionObservation $observation): ?array
    {
        foreach ([
            'youtube-video:' => 'youtube',
            'facebook-post:' => 'facebook',
            'discord-message:' => 'discord',
            'x-post:' => 'x',
            'instagram-media:' => 'instagram',
            'reddit-post:' => 'reddit',
        ] as $prefix => $provider) {
            if (! str_starts_with($observation->sourceVersion, $prefix)) {
                continue;
            }
            $itemId = trim(substr($observation->sourceVersion, strlen($prefix)));
            if ($itemId === '' || mb_strlen($itemId) > 255) {
                return null;
            }

            return ['provider' => $provider, 'item_id' => $itemId];
        }

        return null;
    }
}
