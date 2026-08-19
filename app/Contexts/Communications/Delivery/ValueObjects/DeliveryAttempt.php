<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\ValueObjects;

use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;

final readonly class DeliveryAttempt
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $deliveryId,
        public int $recipientUserId,
        public ?string $playerId,
        public DeliveryChannel $channel,
        public int $attemptCount,
        public int $maxAttempts,
        public array $metadata,
    ) {}

    public function title(): string
    {
        $title = $this->metadata['title'] ?? null;

        return is_string($title) && trim($title) !== '' ? trim($title) : 'Kingshot Alliance reminder';
    }

    public function body(): string
    {
        $body = $this->metadata['body'] ?? null;

        return is_string($body) && trim($body) !== '' ? trim($body) : $this->title();
    }

    public function actionUrl(): ?string
    {
        $url = $this->metadata['action_url'] ?? null;

        return is_string($url) && str_starts_with($url, '/') ? $url : null;
    }
}
