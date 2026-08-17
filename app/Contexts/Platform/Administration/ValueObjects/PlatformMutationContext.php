<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\ValueObjects;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;

final readonly class PlatformMutationContext
{
    public function __construct(
        public AccountIdentity $actor,
        public PlatformAdministrator $grant,
    ) {}
}
