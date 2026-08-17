<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Registration\Data;

final readonly class RegisteredAccount
{
    public function __construct(
        public int $userId,
        public string $email,
    ) {}
}
