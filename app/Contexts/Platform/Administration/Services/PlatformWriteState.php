<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Services;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use App\Contexts\Platform\Administration\ValueObjects\PlatformWriteContext;
use Illuminate\Support\Facades\DB;
use LogicException;

final class PlatformWriteState
{
    public function lock(AccountIdentity $actor): PlatformWriteContext
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Platform write state must be acquired inside a database transaction.');
        }

        $grant = PlatformAdministrator::query()
            ->where('user_id', $actor->userId)
            ->whereNull('revoked_at')
            ->lockForUpdate()
            ->first();

        return new PlatformWriteContext($actor, $grant);
    }
}
