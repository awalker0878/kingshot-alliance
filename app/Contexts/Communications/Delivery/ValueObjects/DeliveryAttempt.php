<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\ValueObjects;

use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;

final readonly class DeliveryAttempt
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $deliveryId,
        public string $messageId,
        public int $recipientUserId,
        public ?string $playerId,
        public DeliveryChannel $channel,
        public ?string $endpointId,
        public int $attemptCount,
        public int $maxAttempts,
        public string $notificationType,
        public string $messageTitle,
        public ?string $messageBody,
        public ?string $messageActionUrl,
        public array $metadata,
    ) {}

    public function title(): string
    {
        $title = trim($this->messageTitle);

        return $title !== '' ? $title : 'Kingshot Alliance notification';
    }

    public function body(): string
    {
        $body = trim((string) $this->messageBody);

        return $body !== '' ? $body : $this->title();
    }

    public function actionUrl(): ?string
    {
        $url = $this->messageActionUrl;

        return is_string($url)
            && str_starts_with($url, '/')
            && ! str_starts_with($url, '//')
            ? $url
            : null;
    }
}
