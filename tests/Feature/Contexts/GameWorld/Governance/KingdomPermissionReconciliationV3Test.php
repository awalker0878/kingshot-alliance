<?php

declare(strict_types=1);

namespace Tests\Feature\Contexts\GameWorld\Governance;

use App\Contexts\GameWorld\Governance\Actions\ReconcileKingdomRolePermissions;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Shared\Infrastructure\Access\Models\Permission;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingdomPermissionReconciliationV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_exact_owner_reconciliation_removes_stale_owned_permissions_and_preserves_foreign_owner_permissions(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $administrator = $factory->player($owner->userId, 13100);
        $kingdom = $factory->kingdom(13100);
        app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $administrator->playerId);
        $role = KingdomRole::query()->where('kingdom_id', $kingdom->kingdomId)->where('key', DefaultKingdomRole::Administrator->value)->firstOrFail();

        app(ReconcileKingdomRolePermissions::class)->handle(
            $kingdom->kingdomId,
            OperationsPermission::ownerKey(),
            [(string) $role->id => [OperationsPermission::EventKingdomView->key()]],
        );
        $role->load('permissions');
        $keys = $role->permissions->pluck('key')->map('strval')->all();
        self::assertContains(KingdomPermission::RoleManage->key(), $keys);
        self::assertContains(OperationsPermission::EventKingdomView->key(), $keys);
        self::assertNotContains(OperationsPermission::EventKingdomManage->key(), $keys);

        $before = AuditEvent::query()->where('event', 'kingdom.role_permissions_reconciled')->count();
        app(ReconcileKingdomRolePermissions::class)->handle(
            $kingdom->kingdomId,
            OperationsPermission::ownerKey(),
            [(string) $role->id => [OperationsPermission::EventKingdomView->key()]],
        );
        self::assertSame($before, AuditEvent::query()->where('event', 'kingdom.role_permissions_reconciled')->count());
    }

    public function test_reconciliation_rejects_unknown_permission_and_role_from_another_kingdom(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $administrator = $factory->player($owner->userId, 13110);
        $otherAdministrator = $factory->player($owner->userId, 13111);
        $kingdom = $factory->kingdom(13110);
        $otherKingdom = $factory->kingdom(13111);
        app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $administrator->playerId);
        app(BootstrapKingdomAdministrator::class)->handle($otherKingdom->kingdomId, $otherAdministrator->playerId);
        $role = KingdomRole::query()->where('kingdom_id', $kingdom->kingdomId)->where('key', DefaultKingdomRole::Administrator->value)->firstOrFail();
        $otherRole = KingdomRole::query()->where('kingdom_id', $otherKingdom->kingdomId)->where('key', DefaultKingdomRole::Administrator->value)->firstOrFail();

        Permission::query()->create(['key' => 'test.unknown-owner', 'owner_key' => 'other', 'description' => 'test']);
        try {
            app(ReconcileKingdomRolePermissions::class)->handle($kingdom->kingdomId, OperationsPermission::ownerKey(), [(string) $role->id => ['missing.permission']]);
            self::fail('Expected unknown permission to fail.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        }

        $this->expectException(RuntimeException::class);
        app(ReconcileKingdomRolePermissions::class)->handle($kingdom->kingdomId, KingdomPermission::ownerKey(), [(string) $otherRole->id => [KingdomPermission::RoleManage->key()]]);
    }
}
