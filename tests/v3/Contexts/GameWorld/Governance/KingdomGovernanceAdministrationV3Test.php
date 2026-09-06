<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Governance;

use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\BulkKingdomRoleAdministration;
use App\Contexts\GameWorld\Governance\Actions\CreateKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\RemoveKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\UpdateKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\ReadModels\KingdomGovernance\Queries\KingdomGovernanceHealthQuery;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use App\Workflows\KingdomGovernance\Actions\ReconcileKingdomGovernancePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class KingdomGovernanceAdministrationV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_custom_role_delegation_is_bounded_by_actor_authority_and_updates_effective_authority(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $administrator = $factory->player($owner->userId, 13200);
        $target = $factory->player($owner->userId, 13200);
        $kingdom = $factory->kingdom(13200);
        app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $administrator->playerId);

        $roleId = app(CreateKingdomRole::class)->handle(
            $administrator->playerId,
            $kingdom->kingdomId,
            'Deputy Governance',
            'Delegated governance administration',
            [KingdomPermission::RoleManage->key()],
        );
        $assignmentId = app(AssignKingdomRole::class)->handle($administrator->playerId, $kingdom->kingdomId, $target->playerId, $roleId, null, null, 'delegated governance');
        self::assertTrue(app(KingdomAuthorization::class)->allows($target->playerId, $kingdom->kingdomId, KingdomPermission::RoleManage));

        app(UpdateKingdomRole::class)->handle($administrator->playerId, $kingdom->kingdomId, $roleId, 'Deputy Governance', 'No longer delegated governance', []);
        self::assertFalse(app(KingdomAuthorization::class)->allows($target->playerId, $kingdom->kingdomId, KingdomPermission::RoleManage));
        self::assertSame($assignmentId, (string) KingdomRoleAssignment::query()->findOrFail($assignmentId)->id);
    }

    public function test_bulk_preview_and_commit_are_bounded_and_report_ineligible_players(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $administrator = $factory->player($owner->userId, 13210);
        $first = $factory->player($owner->userId, 13210);
        $second = $factory->player($owner->userId, 13210);
        $outsider = $factory->player($owner->userId, 13211);
        $kingdom = $factory->kingdom(13210);
        app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $administrator->playerId);
        $viewer = KingdomRole::query()->where('kingdom_id', $kingdom->kingdomId)->where('key', DefaultKingdomRole::Viewer->value)->firstOrFail();

        $preview = app(BulkKingdomRoleAdministration::class)->preview($administrator->playerId, $kingdom->kingdomId, (string) $viewer->id, 'assign', [$first->playerId, $second->playerId, $outsider->playerId]);
        self::assertEqualsCanonicalizing([$first->playerId, $second->playerId], $preview['eligible']);
        self::assertArrayHasKey($outsider->playerId, $preview['ineligible']);

        $result = app(BulkKingdomRoleAdministration::class)->handle($administrator->playerId, $kingdom->kingdomId, (string) $viewer->id, 'assign', [$first->playerId, $second->playerId, $outsider->playerId], 'bulk viewers');
        self::assertCount(2, $result['applied']);
        self::assertArrayHasKey($outsider->playerId, $result['skipped']);
    }

    public function test_revoked_role_no_longer_blocks_player_kingdom_move(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $administrator = $factory->player($owner->userId, 13220);
        $target = $factory->player($owner->userId, 13220);
        $kingdom = $factory->kingdom(13220);
        $destination = $factory->kingdom(13221);
        app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $administrator->playerId);
        $viewer = KingdomRole::query()->where('kingdom_id', $kingdom->kingdomId)->where('key', DefaultKingdomRole::Viewer->value)->firstOrFail();
        $assignmentId = app(AssignKingdomRole::class)->handle($administrator->playerId, $kingdom->kingdomId, $target->playerId, (string) $viewer->id);
        app(RemoveKingdomRole::class)->handle($administrator->playerId, $kingdom->kingdomId, $assignmentId, 'leaving kingdom');

        $moved = app(PersistPlayerIdentity::class)->handle($destination->kingdomId, $target->currentName, $target->gamePlayerId, $target->playerId);
        self::assertSame($destination->kingdomId, $moved->kingdomId);
    }

    public function test_health_detects_owner_policy_drift_and_reconciliation_repairs_system_policy(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $administrator = $factory->player($owner->userId, 13230);
        $kingdom = $factory->kingdom(13230);
        app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $administrator->playerId);
        self::assertSame('healthy', app(KingdomGovernanceHealthQuery::class)->forKingdom($kingdom->kingdomId)['status']);

        $role = KingdomRole::query()->where('kingdom_id', $kingdom->kingdomId)->where('key', DefaultKingdomRole::Viewer->value)->with('permissions')->firstOrFail();
        $operationsPermission = $role->permissions->first();
        self::assertNotNull($operationsPermission);
        $role->permissions()->detach($operationsPermission->id);
        self::assertSame('degraded', app(KingdomGovernanceHealthQuery::class)->forKingdom($kingdom->kingdomId)['status']);

        app(ReconcileKingdomGovernancePolicy::class)->handle($administrator->playerId, $kingdom->kingdomId);
        self::assertSame('healthy', app(KingdomGovernanceHealthQuery::class)->forKingdom($kingdom->kingdomId)['status']);
    }
}
