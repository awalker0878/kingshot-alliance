<?php

declare(strict_types=1);

namespace App\Domain\Platform\ValueObjects;

use App\Contexts\Accounts\Models\User;
use App\Domain\Platform\Models\PlatformAdministrator;

final readonly class PlatformMutationContext
{
    public function __construct(
        public User $actor,
        public PlatformAdministrator $grant,
    ) {}
}
