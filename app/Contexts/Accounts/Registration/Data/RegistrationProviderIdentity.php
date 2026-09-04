<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Registration\Data;

final readonly class RegistrationProviderIdentity
{
    public function __construct(
        public string $provider,
        public string $subject,
        public ?string $email,
        public bool $emailVerified,
    ) {}
}
