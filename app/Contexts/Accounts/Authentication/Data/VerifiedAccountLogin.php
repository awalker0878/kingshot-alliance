<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Data;

final readonly class VerifiedAccountLogin
{
    /** @param 'password'|'google'|'passkey' $method */
    public function __construct(
        public int $userId,
        public string $method,
        public ?string $credentialReference,
        public string $credentialFingerprint,
        public string $accountFingerprint,
        public bool $requiresMultiFactor,
    ) {}
}
