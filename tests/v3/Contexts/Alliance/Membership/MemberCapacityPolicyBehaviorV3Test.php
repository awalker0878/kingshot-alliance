<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Membership;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Policies\MemberCapacityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class MemberCapacityPolicyBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_member_capacity_enforces_the_current_plan_entitlement(): void
    {
        $factory = new ScenarioFactory;
        $account = $factory->account();
        $allianceRef = $factory->alliance($factory->player($account->userId));
        $alliance = Alliance::query()->findOrFail($allianceRef->allianceId);

        DB::table('platform_plan_entitlements')
            ->where('plan_code', 'standard')
            ->where('entitlement_key', 'members.max')
            ->update([
                'limit_value' => 1,
                'updated_at' => now(),
            ]);

        $this->expectException(ValidationException::class);
        app(MemberCapacityPolicy::class)->assertCapacity($alliance);
    }
}
