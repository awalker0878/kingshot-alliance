<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Access\Services;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Platform\Access\Models\PlatformAdministrator;
use App\Contexts\Platform\Access\ValueObjects\PlatformMutationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class PlatformMutationAuthority
{
    /**
     * Acquire current Platform Administrator authority for a Platform mutation.
     *
     * The active grant row is the authority anchor. Grant/revoke workflows must lock
     * the same row when changing an administrator's authority.
     */
    public function require(User $actor): PlatformMutationContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Platform mutation authority must be acquired inside a database transaction.');
        }

        $grant = PlatformAdministrator::query()
            ->where('user_id', $actor->id)
            ->whereNull('revoked_at')
            ->lockForUpdate()
            ->first();

        if (! $grant instanceof PlatformAdministrator) {
            throw new AuthorizationException('Platform administrator access is required.');
        }

        $currentActor = User::query()->whereKey($actor->id)->firstOrFail();

        return new PlatformMutationContext($currentActor, $grant);
    }
}
