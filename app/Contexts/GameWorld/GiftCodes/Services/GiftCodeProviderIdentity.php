<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final readonly class GiftCodeProviderIdentity
{
    /** @param array<string,string> $attributes */
    public function __construct(
        public string $provider,
        public array $attributes,
    ) {}
}
