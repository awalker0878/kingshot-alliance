<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Services;

use App\Contexts\Platform\Administration\Models\PlatformAdministrator;

final class PlatformAdministratorDirectory
{
    public function lastActiveUserId(): int
    {
        return (int) PlatformAdministrator::query()->whereNull('revoked_at')->max('user_id');
    }

    /** @return list<int> */
    public function activeUserIdsAfter(int $after, int $through, int $limit = 25): array
    {
        return array_values(PlatformAdministrator::query()->whereNull('revoked_at')
            ->where('user_id', '>', $after)->where('user_id', '<=', $through)
            ->orderBy('user_id')->limit(max(1, min(25, $limit)))->pluck('user_id')
            ->map(static fn (mixed $value): int => (int) $value)->all());
    }
}
