<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Platform\Access;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use App\Contexts\Platform\Access\Models\PlatformAdministrator;
use App\Contexts\Platform\Actions\ManagePlatformAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v2\Support\ScenarioFactory;
use Tests\v2\TestCase;

final class PlatformAdministratorIsolationV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_platform_administration_grant_is_not_game_domain_authority(): void
    {
        $factory = new ScenarioFactory;
        $kingdom = $factory->kingdom(18001);
        $administrator = $factory->user();
        $administratorPlayer = $factory->player($administrator, 18001);
        $allianceOwner = $factory->user();
        $allianceOwnerPlayer = $factory->player($allianceOwner, 18001);
        $alliance = $factory->alliance($allianceOwnerPlayer);

        app(ManagePlatformAdministrator::class)->grant($administrator);
        self::assertTrue(PlatformAdministrator::activeFor($administrator));

        self::assertFalse(app(AllianceAuthorization::class)->allows(
            $administratorPlayer,
            $alliance,
            AlliancePermission::View,
        ));
        self::assertFalse(app(AllianceOperationsAuthorization::class)->allows(
            $administratorPlayer,
            $alliance,
            OperationsPermission::EventAllianceView,
        ));
        self::assertFalse(app(KingdomAuthorization::class)->allows(
            $administratorPlayer,
            $kingdom,
            KingdomPermission::RoleManage,
        ));
    }
}
