<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\Governance\Feature;

use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\BulkKingdomRoleAdministration;
use App\Contexts\GameWorld\Governance\Actions\CreateKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\UpdateKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingdomRoleDelegationTest extends TestCase
{
    use RefreshDatabase;

    public function test_limited_role_manager_can_delegate_held_permissions_but_cannot_assign_stronger_roles_even_on_replay(): void
    {
        [$admin, $adminRole] = $this->administrator();
        $factory = app(ScenarioFactory::class);
        $deputy = $factory->unclaimedPlayer(61620);
        $target = $factory->unclaimedPlayer(61620);
        $role = app(CreateKingdomRole::class)->handle($admin->playerId, $admin->kingdomId, 'Limited manager', null, [KingdomPermission::RoleManage->key()]);
        $assign = app(AssignKingdomRole::class);
        $assign->handle($admin->playerId, $admin->kingdomId, $deputy->playerId, $role);
        $held = $assign->handle($deputy->playerId, $admin->kingdomId, $target->playerId, $role);
        self::assertSame($held, $assign->handle($deputy->playerId, $admin->kingdomId, $target->playerId, $role));
        foreach ([$deputy->playerId, $target->playerId, $admin->playerId] as $player) {
            $before = DB::table('kingdom_role_assignments')->count();
            try {
                $assign->handle($deputy->playerId, $admin->kingdomId, $player, $adminRole);
                self::fail('RoleManage alone cannot grant stronger Operations or administrator authority.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('permissions', $exception->errors());
            }
            self::assertSame($before, DB::table('kingdom_role_assignments')->count());
        }
        self::assertFalse(app(KingdomAuthorityFactsQuery::class)->findCurrent($deputy->playerId, $admin->kingdomId)?->hasPermissionObservedAtRead(OperationsPermission::EventKingdomManage->value));
    }

    /** @return iterable<string,array{string,?string,array<mixed>,string}> */
    public static function invalidDefinitions(): iterable
    {
        yield 'blank name' => [' ', null, [], 'name'];
        yield 'no stable name key' => ['---', null, [], 'name'];
        yield 'name over Unicode bound' => [str_repeat('界', 101), null, [], 'name'];
        yield 'description over Unicode bound' => ['Valid', str_repeat('界', 256), [], 'description'];
        yield 'permission object' => ['Valid', null, ['key' => 'kingdom.roles.manage'], 'permissions'];
        yield 'permission value type' => ['Valid', null, [false], 'permissions'];
        yield 'empty permission' => ['Valid', null, [' '], 'permissions'];
        yield 'permission key length' => ['Valid', null, [str_repeat('a', 101)], 'permissions'];
        yield 'permission input count' => ['Valid', null, array_fill(0, 51, 'kingdom.roles.manage'), 'permissions'];
        yield 'unrecognized permission' => ['Valid', null, ['unowned.permission'], 'permissions'];
    }

    /** @param array<mixed> $keys */
    #[DataProvider('invalidDefinitions')]
    public function test_direct_create_and_update_reject_invalid_definitions_without_mutation(string $name, ?string $description, array $keys, string $field): void
    {
        [$admin] = $this->administrator();
        $create = app(CreateKingdomRole::class);
        $id = $create->handle($admin->playerId, $admin->kingdomId, 'Existing', null, []);
        $before = [DB::table('kingdom_roles')->count(), DB::table('audit_events')->count(), DB::table('outbox_messages')->count()];
        foreach ([true, false] as $new) {
            try {
                if ($new) {
                    $create->handle($admin->playerId, $admin->kingdomId, $name, $description, $keys);
                } else {
                    app(UpdateKingdomRole::class)->handle($admin->playerId, $admin->kingdomId, $id, $name, $description, $keys);
                }
                self::fail('The owner must reject malformed direct input.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey($field, $exception->errors());
            }
        }
        self::assertSame('Existing', KingdomRole::query()->findOrFail($id)->name);
        self::assertSame($before, [DB::table('kingdom_roles')->count(), DB::table('audit_events')->count(), DB::table('outbox_messages')->count()]);
    }

    public function test_maximum_names_get_distinct_database_safe_keys_and_permissions_are_normalized(): void
    {
        [$admin] = $this->administrator();
        $create = app(CreateKingdomRole::class);
        $keys = [];
        foreach (['a', 'b'] as $suffix) {
            $id = $create->handle($admin->playerId, $admin->kingdomId, str_repeat('x', 99).$suffix, str_repeat('界', 255), [' '.KingdomPermission::RoleManage->key().' ', KingdomPermission::RoleManage->key()]);
            $role = KingdomRole::query()->findOrFail($id);
            self::assertLessThanOrEqual(64, strlen($role->key));
            self::assertSame(1, $role->permissions()->count());
            $keys[] = $role->key;
        }
        self::assertNotSame($keys[0], $keys[1]);
    }

    public function test_alias_or_archived_scope_has_no_read_or_write_authority_and_alias_target_is_rejected(): void
    {
        [$admin, $role] = $this->administrator();
        $other = app(ScenarioFactory::class)->unclaimedPlayer(61620);
        $facts = app(KingdomAuthorityFactsQuery::class);
        DB::table('players')->where('id', $other->playerId)->update(['canonical_player_id' => $admin->playerId]);
        try {
            app(AssignKingdomRole::class)->handle($admin->playerId, $admin->kingdomId, $other->playerId, $role);
            self::fail('Alias targets are not current Governors.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('player_id', $exception->errors());
        }
        DB::table('players')->where('id', $other->playerId)->update(['canonical_player_id' => null]);
        foreach (['alias', 'archived'] as $state) {
            DB::table('players')->where('id', $admin->playerId)->update(['canonical_player_id' => $state === 'alias' ? $other->playerId : null]);
            DB::table('kingdoms')->where('id', $admin->kingdomId)->update(['status' => $state === 'archived' ? 'archived' : 'active']);
            self::assertNull($facts->findCurrent($admin->playerId, $admin->kingdomId));
            self::assertNull(DB::transaction(fn () => $facts->lockCurrent($admin->playerId, $admin->kingdomId)));
            self::assertFalse(app(KingdomAuthorization::class)->allows($admin->playerId, $admin->kingdomId, KingdomPermission::RoleManage));
            try {
                app(CreateKingdomRole::class)->handle($admin->playerId, $admin->kingdomId, 'Rejected', null, []);
                self::fail('Withdrawn current scope must reject writes.');
            } catch (AuthorizationException) {
                self::assertSame(0, DB::table('kingdom_roles')->where('name', 'Rejected')->count());
            }
        }
    }

    public function test_assignment_history_does_not_expand_authority_hydration(): void
    {
        [$admin, $role] = $this->administrator();
        $rows = [];
        for ($i = 0; $i < 1001; $i++) {
            $rows[] = ['id' => strtolower((string) Str::ulid()), 'kingdom_id' => $admin->kingdomId, 'player_id' => $admin->playerId, 'kingdom_role_id' => $role, 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('kingdom_role_assignments')->insert($rows);
        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $facts = DB::transaction(fn () => app(KingdomAuthorityFactsQuery::class)->lockCurrent($admin->playerId, $admin->kingdomId));
        self::assertNotNull($facts);
        self::assertCount(6, $facts->permissionKeysObservedAtRead);
        self::assertCount(3, $queries);
        self::assertStringContainsString('from "kingdoms"', $queries[0]);
        self::assertStringContainsString('for share', $queries[0]);
        self::assertStringContainsString('from "players"', $queries[1]);
        self::assertStringContainsString('select "key" from "permissions"', $queries[2]);
        self::assertStringContainsString('limit 501', $queries[2]);
    }

    public function test_direct_assignment_rejects_invalid_dates_and_oversized_reason_before_writing(): void
    {
        [$admin, $role] = $this->administrator();
        $target = app(ScenarioFactory::class)->unclaimedPlayer(61620);
        foreach ([['not-a-date', null, null, 'effective_from'], [null, 'tomorrow', null, 'expires_at'], [null, null, str_repeat('界', 501), 'reason']] as [$from, $until, $reason, $field]) {
            try {
                app(AssignKingdomRole::class)->handle($admin->playerId, $admin->kingdomId, $target->playerId, $role, $from, $until, $reason);
                self::fail('Invalid direct input must be rejected.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey($field, $exception->errors());
            }
        }
        self::assertSame(0, DB::table('kingdom_role_assignments')->where('player_id', $target->playerId)->count());
        $id = app(AssignKingdomRole::class)->handle($admin->playerId, $admin->kingdomId, $target->playerId, $role, null, null, str_repeat('界', 500));
        self::assertSame(str_repeat('界', 500), DB::table('kingdom_role_assignments')->where('id', $id)->value('reason'));
    }

    public function test_bulk_raw_input_is_bounded_before_deduplication_and_aliases_are_ineligible(): void
    {
        [$admin, $role] = $this->administrator();
        $target = app(ScenarioFactory::class)->unclaimedPlayer(61620);
        $bulk = app(BulkKingdomRoleAdministration::class);
        foreach ([array_fill(0, 51, $target->playerId), [false], ['target' => $target->playerId]] as $ids) {
            try {
                $bulk->handle($admin->playerId, $admin->kingdomId, $role, 'assign', $ids);
                self::fail('Raw bulk input must satisfy the owner bound.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('players', $exception->errors());
            }
        }
        DB::table('players')->where('id', $target->playerId)->update(['canonical_player_id' => $admin->playerId]);
        $result = $bulk->handle($admin->playerId, $admin->kingdomId, $role, 'assign', [$target->playerId]);
        self::assertSame([], $result['applied']);
        self::assertArrayHasKey($target->playerId, $result['skipped']);
        self::assertSame(0, DB::table('kingdom_role_assignments')->where('player_id', $target->playerId)->count());
    }

    /** @return array{PlayerReference,string} */
    private function administrator(): array
    {
        $admin = app(ScenarioFactory::class)->unclaimedPlayer(61620);
        $roles = app(BootstrapKingdomAdministrator::class)->handle($admin->kingdomId, $admin->playerId);

        return [$admin, $roles->administratorRoleId];
    }
}
