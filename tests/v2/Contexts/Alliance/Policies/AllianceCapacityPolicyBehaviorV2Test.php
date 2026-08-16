<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Alliance\Policies;

use App\Contexts\Alliance\Policies\AllianceCapacityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\v2\Support\ScenarioFactory;
use Tests\v2\TestCase;

final class AllianceCapacityPolicyBehaviorV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_member_capacity_enforces_the_current_plan_entitlement(): void
    {
        $factory = new ScenarioFactory;
        $alliance = $factory->alliance($factory->player($factory->user()));

        DB::table('platform_plan_entitlements')
            ->where('plan_code', 'standard')
            ->where('entitlement_key', 'members.max')
            ->update([
                'limit_value' => 1,
                'updated_at' => now(),
            ]);

        $this->expectException(ValidationException::class);

        app(AllianceCapacityPolicy::class)->assertMemberCapacity($alliance);
    }
}
