<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeSourceOperationalAlertPolicy
{
    public function shouldAlert(string $healthStatus, int $consecutiveQuarantinedRuns = 0, int $reconciliationGapCount = 0): bool
    {
        return in_array($healthStatus, [
            'authentication_failed',
            'permission_revoked',
            'contract_changed',
            'parser_failed',
            'failing',
        ], true)
            || $consecutiveQuarantinedRuns >= 3
            || $reconciliationGapCount > 0;
    }
}
