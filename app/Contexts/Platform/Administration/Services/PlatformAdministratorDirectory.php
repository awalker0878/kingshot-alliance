<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Services;

use App\Contexts\Platform\Administration\Models\PlatformAdministrator;

final class PlatformAdministratorDirectory
{
    /** @return list<int> */
    public function activeUserIds(): array
    {
        return array_values(
            PlatformAdministrator::query()
                ->whereNull('revoked_at')
                ->orderBy('user_id')
                ->pluck('user_id')
                ->map(static fn (mixed $value): int => (int) $value)
                ->filter(static fn (int $value): bool => $value > 0)
                ->all(),
        );
    }
}
