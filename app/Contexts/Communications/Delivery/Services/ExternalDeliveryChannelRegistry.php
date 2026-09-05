<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Services;

use App\Contexts\Communications\Delivery\Channels\DiscordWebhookChannel;
use App\Contexts\Communications\Delivery\Channels\EmailDeliveryChannel;
use App\Contexts\Communications\Delivery\Channels\TelegramBotChannel;
use App\Contexts\Communications\Delivery\Channels\WebPushChannel;
use App\Contexts\Communications\Delivery\Contracts\ExternalDeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;

final readonly class ExternalDeliveryChannelRegistry
{
    public function __construct(
        private DiscordWebhookChannel $discord,
        private TelegramBotChannel $telegram,
        private WebPushChannel $webPush,
        private EmailDeliveryChannel $email,
    ) {}

    public function for(DeliveryChannel $channel): ?ExternalDeliveryChannel
    {
        return match ($channel) {
            DeliveryChannel::Discord => $this->discord,
            DeliveryChannel::Telegram => $this->telegram,
            DeliveryChannel::WebPush => $this->webPush,
            DeliveryChannel::Email => $this->email,
            DeliveryChannel::InApp => null,
        };
    }
}
