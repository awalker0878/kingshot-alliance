<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeSourceQuarantinePolicy
{
    public function isDegraded(int $examined, int $quarantined): bool
    {
        return $quarantined > 0 || ($examined > 0 && $quarantined >= $examined);
    }
}
