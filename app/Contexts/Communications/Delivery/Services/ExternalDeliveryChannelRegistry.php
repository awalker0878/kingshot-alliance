<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Services;

use App\Contexts\Communications\Delivery\Channels\DiscordWebhookChannel;
use App\Contexts\Communications\Delivery\Channels\TelegramBotChannel;
use App\Contexts\Communications\Delivery\Contracts\ExternalDeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;

final readonly class ExternalDeliveryChannelRegistry
{
    public function __construct(
        private DiscordWebhookChannel $discord,
        private TelegramBotChannel $telegram,
    ) {}

    public function for(DeliveryChannel $channel): ?ExternalDeliveryChannel
    {
        return match ($channel) {
            DeliveryChannel::Discord => $this->discord,
            DeliveryChannel::Telegram => $this->telegram,
            DeliveryChannel::InApp => null,
        };
    }
}
