<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final readonly class GiftCodeProviderPublication
{
    public function __construct(
        public string $provider,
        public string $providerItemId,
        public string $sourceUrl,
        public string $content,
        public ?string $publishedAt,
        public ?string $updatedAt = null,
        public ?string $retrievalVersion = null,
    ) {}
}
