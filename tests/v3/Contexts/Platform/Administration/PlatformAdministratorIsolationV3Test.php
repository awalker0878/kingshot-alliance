<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Platform\Administration;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class PlatformAdministratorIsolationV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_platform_administration_grant_is_not_game_domain_authority(): void
    {
        $factory = new ScenarioFactory;
        $kingdom = $factory->kingdom(18001);
        $administrator = $factory->account();
        $administratorPlayer = $factory->player($administrator->userId, 18001);
        $allianceOwner = $factory->account();
        $allianceOwnerPlayer = $factory->player($allianceOwner->userId, 18001);
        $alliance = $factory->alliance($allianceOwnerPlayer);

        app(ManagePlatformAdministrator::class)->grant($administrator->userId);
        self::assertTrue(PlatformAdministrator::activeForUserId($administrator->userId));

        self::assertFalse(app(AllianceAuthorization::class)->allows(
            $administratorPlayer->playerId,
            $alliance->allianceId,
            AlliancePermission::View,
        ));
        self::assertFalse(app(AllianceOperationsAuthorization::class)->allows(
            $administratorPlayer->playerId,
            $alliance->allianceId,
            OperationsPermission::EventAllianceView,
        ));
        self::assertFalse(app(KingdomAuthorization::class)->allows(
            $administratorPlayer->playerId,
            $kingdom->kingdomId,
            KingdomPermission::RoleManage,
        ));
    }
}
