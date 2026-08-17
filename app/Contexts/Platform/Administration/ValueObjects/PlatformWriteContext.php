<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\ValueObjects;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;

/** Immutable transaction-time Platform authority facts. */
final readonly class PlatformWriteContext
{
    public function __construct(
        public AccountIdentity $actor,
        public ?string $grantId,
    ) {}
}
