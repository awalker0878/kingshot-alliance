<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Policies;

use App\Contexts\Alliance\Content\Models\MediaAsset;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StorageCapacityPolicy
{
    public function assertCapacity(Alliance $alliance, int $additionalBytes): void
    {
        $current = (int) MediaAsset::query()->where('alliance_id', $alliance->id)->sum('size_bytes');
        $limit = $this->limit($alliance, 'storage.bytes.max');
        if ($additionalBytes < 0 || $current + $additionalBytes > $limit) {
            throw ValidationException::withMessages(['media' => 'The alliance storage quota would be exceeded by this upload.']);
        }
    }

    private function limit(Alliance $alliance, string $key): int
    {
        $planCode = DB::table('alliance_plan_assignments')->where('alliance_id', $alliance->id)->value('plan_code');
        $planCode = is_string($planCode) && $planCode !== '' ? $planCode : 'standard';
        $value = DB::table('platform_plan_entitlements')->where('plan_code', $planCode)->where('entitlement_key', $key)->value('limit_value');
        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['plan' => sprintf('The current plan does not define the %s entitlement.', $key)]);
        }

        return max(0, (int) $value);
    }
}
