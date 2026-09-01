<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\ValueObjects;

final readonly class ProviderIdentity
{
    public function __construct(
        public int $identityId,
        public int $userId,
        public string $provider,
        public string $providerSubject,
        public ?string $providerEmail,
        public bool $providerEmailVerified,
    ) {}
}
