<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Policies;

use App\Contexts\Alliance\Content\Models\MediaAsset;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\Invitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AllianceCapacityPolicy
{
    public function assertMemberCapacity(Alliance $alliance): void
    {
        $active = AllianceMembership::query()->where('alliance_id', $alliance->id)->where('status', MembershipStatus::Active->value)->count();
        $pending = Invitation::query()->where('alliance_id', $alliance->id)->where('status', InvitationStatus::Pending->value)->where('expires_at', '>', now())->count();
        $this->assertBelow($active + $pending, $this->limit($alliance, 'members.max'), 'members');
    }

    public function assertStorageCapacity(Alliance $alliance, int $additionalBytes): void
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

    private function assertBelow(int $current, int $limit, string $label): void
    {
        if ($current >= $limit) {
            throw ValidationException::withMessages(['quota' => sprintf('The alliance has reached its plan limit for %s (%d).', $label, $limit)]);
        }
    }
}
