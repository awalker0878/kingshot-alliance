<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Access\Services;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Platform\Access\Models\PlatformAdministrator;
use App\Contexts\Platform\Access\ValueObjects\PlatformWriteContext;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Policy-free transaction-time state acquisition for Platform writes. */
final class PlatformWriteState
{
    public function lock(User $actor): PlatformWriteContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Platform write state must be acquired inside a database transaction.');
        }

        $currentActor = User::query()
            ->whereKey($actor->id)
            ->lockForUpdate()
            ->firstOrFail();

        $grant = PlatformAdministrator::query()
            ->where('user_id', $currentActor->id)
            ->whereNull('revoked_at')
            ->lockForUpdate()
            ->first();

        return new PlatformWriteContext($currentActor, $grant);
    }
}
