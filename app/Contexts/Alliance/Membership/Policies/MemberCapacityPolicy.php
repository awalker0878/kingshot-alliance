<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Policies;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\Invitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MemberCapacityPolicy
{
    public function assertCapacity(Alliance $alliance): void
    {
        $active = AllianceMembership::query()->where('alliance_id',$alliance->id)->where('status',MembershipStatus::Active->value)->count();
        $pending = Invitation::query()->where('alliance_id',$alliance->id)->where('status',InvitationStatus::Pending->value)->where('expires_at','>',now())->count();
        $limit = $this->limit($alliance,'members.max');
        if ($active + $pending >= $limit) {
            throw ValidationException::withMessages(['quota'=>sprintf('The alliance has reached its plan limit for members (%d).',$limit)]);
        }
    }

    private function limit(Alliance $alliance, string $key): int
    {
        $planCode=DB::table('alliance_plan_assignments')->where('alliance_id',$alliance->id)->value('plan_code');
        $planCode=is_string($planCode)&&$planCode!==''?$planCode:'standard';
        $value=DB::table('platform_plan_entitlements')->where('plan_code',$planCode)->where('entitlement_key',$key)->value('limit_value');
        if(!is_numeric($value))throw ValidationException::withMessages(['plan'=>sprintf('The current plan does not define the %s entitlement.',$key)]);
        return max(0,(int)$value);
    }
}
