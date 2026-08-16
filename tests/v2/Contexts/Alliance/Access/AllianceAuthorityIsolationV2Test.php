<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Alliance\Access;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v2\Support\ScenarioFactory;
use Tests\v2\TestCase;

final class AllianceAuthorityIsolationV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_authority_is_not_aggregated_across_players_owned_by_one_user(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->user();
        $leader = $factory->player($user, 17001);
        $alliance = $factory->alliance($leader);
        $otherPersona = $factory->player($user, 17001);
        $authorization = app(AllianceAuthorization::class);

        self::assertTrue($authorization->allows($leader, $alliance, AlliancePermission::Manage));
        self::assertFalse($authorization->allows($otherPersona, $alliance, AlliancePermission::View));
        self::assertFalse($authorization->allows($otherPersona, $alliance, AlliancePermission::Manage));
    }
}
