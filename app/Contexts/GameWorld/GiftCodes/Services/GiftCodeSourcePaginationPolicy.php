<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Adapters\FacebookPageGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\InstagramMediaGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\RedditSubredditGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\YouTubeChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeSourceCheckpoint;

final class GiftCodeSourcePaginationPolicy
{
    public function isOpaqueHeadPaged(?string $adapterKey): bool
    {
        return in_array($adapterKey, [
            YouTubeChannelGiftCodeSourceAdapter::KEY,
            RedditSubredditGiftCodeSourceAdapter::KEY,
            FacebookPageGiftCodeSourceAdapter::KEY,
            InstagramMediaGiftCodeSourceAdapter::KEY,
        ], true);
    }

    /** @return list<string> */
    public function providerItemIds(?GiftCodeSourceCheckpoint $checkpoint): array
    {
        $values = $checkpoint?->providerState['provider_item_ids'] ?? [];
        if (! is_array($values)) {
            return [];
        }

        $result = [];
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                $result[] = trim($value);
            }
        }

        return array_values(array_unique($result));
    }

    public function latestProviderId(?GiftCodeSourceCheckpoint $checkpoint): ?string
    {
        if ($checkpoint === null) {
            return null;
        }
        foreach ([
            'latest_video_id',
            'latest_post_fullname',
            'latest_post_id',
            'latest_media_id',
            'latest_publication_id',
            'message_high_water',
        ] as $key) {
            $value = $checkpoint->providerState[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
