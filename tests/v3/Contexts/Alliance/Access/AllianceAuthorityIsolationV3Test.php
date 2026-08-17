<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Access;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceAuthorityIsolationV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_authority_is_not_aggregated_across_players_owned_by_one_user(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->account();
        $leader = $factory->player($user->userId, 17001);
        $alliance = $factory->alliance($leader);
        $otherPersona = $factory->player($user->userId, 17001);
        $authorization = app(AllianceAuthorization::class);

        self::assertTrue($authorization->allows($leader->playerId, $alliance->allianceId, AlliancePermission::Manage));
        self::assertFalse($authorization->allows($otherPersona->playerId, $alliance->allianceId, AlliancePermission::View));
        self::assertFalse($authorization->allows($otherPersona->playerId, $alliance->allianceId, AlliancePermission::Manage));
    }
}
