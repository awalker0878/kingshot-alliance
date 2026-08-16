<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Services;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use App\Contexts\Platform\Administration\ValueObjects\PlatformMutationContext;
use App\Contexts\Platform\Administration\ValueObjects\PlatformWriteContext;
use Illuminate\Auth\Access\AuthorizationException;

final class PlatformAuthorization
{
    public function allows(User $actor): bool
    {
        return PlatformAdministrator::query()->where('user_id', $actor->id)->whereNull('revoked_at')->exists();
    }

    public function authorizeContext(PlatformWriteContext $context): PlatformMutationContext
    {
        if (! $context->grant instanceof PlatformAdministrator) {
            throw new AuthorizationException('Platform administrator access is required.');
        }

        return new PlatformMutationContext($context->actor, $context->grant);
    }
}
