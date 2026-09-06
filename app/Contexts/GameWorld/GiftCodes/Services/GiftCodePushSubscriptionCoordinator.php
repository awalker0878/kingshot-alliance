<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Adapters\DiscordChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\FacebookPageGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\OfficialXGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\YouTubeChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSubscription;
use UnexpectedValueException;

final readonly class GiftCodePushSubscriptionCoordinator
{
    public function __construct(
        private YouTubeWebSubSubscriptionManager $youtube,
        private FacebookPageWebhookSubscriptionManager $facebook,
        private XFilteredStreamWebhookSubscriptionManager $x,
    ) {}

    public function subscribe(GiftCodeSourceRegistry $source): GiftCodeSourceSubscription
    {
        if (! $source->push_enabled || ! $source->ingestion_enabled || ! $source->is_active || $source->revoked_at !== null) {
            throw new UnexpectedValueException('The source must be active with ingestion and push acquisition enabled.');
        }

        return match ($source->adapter_key) {
            YouTubeChannelGiftCodeSourceAdapter::KEY => $this->youtube->subscribe($source),
            FacebookPageGiftCodeSourceAdapter::KEY => $this->facebook->subscribe($source),
            OfficialXGiftCodeSourceAdapter::KEY => $this->x->subscribe($source),
            DiscordChannelGiftCodeSourceAdapter::KEY => $this->discordSubscription($source),
            default => throw new UnexpectedValueException('The selected source does not expose a supported push subscription transport.'),
        };
    }

    public function unsubscribe(GiftCodeSourceRegistry $source): GiftCodeSourceSubscription
    {
        return match ($source->adapter_key) {
            YouTubeChannelGiftCodeSourceAdapter::KEY => $this->youtube->unsubscribe($source),
            FacebookPageGiftCodeSourceAdapter::KEY => $this->facebook->unsubscribe($source),
            OfficialXGiftCodeSourceAdapter::KEY => $this->x->unsubscribe($source),
            DiscordChannelGiftCodeSourceAdapter::KEY => $this->disableDiscordSubscription($source),
            default => throw new UnexpectedValueException('The selected source does not expose a supported push subscription transport.'),
        };
    }

    private function discordSubscription(GiftCodeSourceRegistry $source): GiftCodeSourceSubscription
    {
        if (! (bool) config('game_world.gift_codes.discord_gateway_enabled', false)) {
            throw new UnexpectedValueException('Discord Gateway transport is not enabled.');
        }
        $policy = $source->provenance_policy ?? [];

        return GiftCodeSourceSubscription::query()->updateOrCreate(
            [
                'gift_code_source_id' => (string) $source->id,
                'provider' => 'discord',
                'transport' => 'gateway',
            ],
            [
                'topic_or_rule' => 'MESSAGE_CREATE,MESSAGE_UPDATE',
                'configured_identity' => [
                    'guild_id' => $policy['discord_guild_id'] ?? null,
                    'channel_id' => $policy['discord_channel_id'] ?? null,
                ],
                'status' => 'pending',
                'last_error_code' => null,
            ],
        );
    }

    private function disableDiscordSubscription(GiftCodeSourceRegistry $source): GiftCodeSourceSubscription
    {
        $subscription = GiftCodeSourceSubscription::query()->firstOrCreate(
            [
                'gift_code_source_id' => (string) $source->id,
                'provider' => 'discord',
                'transport' => 'gateway',
            ],
            ['status' => 'disabled'],
        );
        $subscription->forceFill(['status' => 'disabled', 'last_error_code' => null])->save();

        return $subscription->refresh();
    }
}
