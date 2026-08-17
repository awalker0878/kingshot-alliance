<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\ValueObjects;

final readonly class IssuedApiCredential
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $credentialId,
        public string $name,
        public string $token,
        public array $scopes,
        public ?string $expiresAt,
    ) {}
}
