<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\Governance\Feature;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use App\Workflows\KingdomGovernance\Actions\RecoverKingdomAdministrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingdomAdministratorRecoveryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_platform_administrator_can_repair_player_scoped_kingdom_administration_without_receiving_game_authority(): void
    {
        $factory = new ScenarioFactory;
        $platformAccount = $factory->account();
        $gameOwner = $factory->account();
        $administrator = $factory->player($gameOwner->userId, 13300);
        $replacement = $factory->player($gameOwner->userId, 13300);
        $kingdom = $factory->kingdom(13300);
        app(ManagePlatformAdministrator::class)->grant($platformAccount->userId);
        app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $administrator->playerId);

        $operator = app(AccountIdentityQuery::class)->require($platformAccount->userId);
        app(RecoverKingdomAdministrator::class)->handle($operator, $kingdom->kingdomId, $replacement->playerId, 'Original administrator account cannot be accessed.', true);

        $authorization = app(KingdomAuthorization::class);
        self::assertFalse($authorization->allows($administrator->playerId, $kingdom->kingdomId, KingdomPermission::RoleManage));
        self::assertTrue($authorization->allows($replacement->playerId, $kingdom->kingdomId, KingdomPermission::RoleManage));
        $audit = AuditEvent::query()->where('event', 'kingdom.administrator_recovered')->latest('id')->firstOrFail();
        self::assertSame((string) $platformAccount->userId, (string) $audit->actor_user_id);
        self::assertNull($audit->actor_player_id);
    }

    public function test_non_platform_account_cannot_invoke_break_glass_recovery(): void
    {
        $factory = new ScenarioFactory;
        $ordinaryAccount = $factory->account();
        $gameOwner = $factory->account();
        $administrator = $factory->player($gameOwner->userId, 13310);
        $replacement = $factory->player($gameOwner->userId, 13310);
        $kingdom = $factory->kingdom(13310);
        app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $administrator->playerId);

        $this->expectException(AuthorizationException::class);
        app(RecoverKingdomAdministrator::class)->handle(
            app(AccountIdentityQuery::class)->require($ordinaryAccount->userId),
            $kingdom->kingdomId,
            $replacement->playerId,
            'Attempted unauthorized recovery should fail.',
            true,
        );
    }
}
