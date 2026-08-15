<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Access\ValueObjects;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Platform\Access\Models\PlatformAdministrator;

final readonly class PlatformMutationContext
{
    public function __construct(
        public User $actor,
        public PlatformAdministrator $grant,
    ) {}
}
