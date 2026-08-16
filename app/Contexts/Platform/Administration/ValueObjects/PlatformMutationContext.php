<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\ValueObjects;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;

final readonly class PlatformMutationContext
{
    public function __construct(public User $actor, public PlatformAdministrator $grant) {}
}
