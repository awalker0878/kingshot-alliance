<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;

final class GiftCodeOfficialSourcePolicy
{
    public function canAutoVerify(GiftCodeSourceRegistry $source): bool
    {
        $policy = $source->provenance_policy ?? [];

        return $source->is_active
            && $source->ingestion_enabled
            && $source->revoked_at === null
            && ($policy['auto_verify'] ?? false) === true
            && ($policy['provider_contract_confirmed'] ?? false) === true;
    }
}
