<?php

declare(strict_types=1);

namespace Tests\Workflows\KingdomGovernance\Feature;

use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\RemoveKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use App\Workflows\KingdomGovernance\Actions\ReconcileKingdomGovernancePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingdomPolicyAtomicityTest extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{bool}> */
    public static function commands(): iterable
    {
        yield 'trusted CLI bootstrap composition' => [true];
        yield 'Governor-authorized policy reconciliation' => [false];
    }

    #[DataProvider('commands')]
    public function test_late_operations_failure_rolls_back_both_owners_and_retry_completes(bool $bootstrap): void
    {
        $factory = app(ScenarioFactory::class);
        $kingdom = $factory->kingdom(61595);
        $actor = $factory->unclaimedPlayer(61595);
        $initialize = app(BootstrapKingdomAdministrator::class);
        if (! $bootstrap) {
            $roles = $initialize->handle($kingdom->kingdomId, $actor->playerId);
            DB::table('kingdom_roles')->where('id', $roles->administratorRoleId)->update(['name' => 'Drifted administrator']);
            DB::table('kingdom_role_permissions')->where('kingdom_role_id', $roles->administratorRoleId)
                ->whereIn('permission_id', DB::table('permissions')->where('owner_key', 'operations')->select('id'))->delete();
        }
        $before = [];
        foreach (['kingdom_roles', 'kingdom_role_assignments', 'kingdom_role_permissions', 'permissions', 'audit_events', 'outbox_messages'] as $table) {
            $before[$table] = DB::table($table)->get()->map(static fn ($row): array => (array) $row)->all();
        }
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "permissions"') && in_array('operations', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected policy provisioning failure.');
            }
        });
        $run = static function () use ($bootstrap, $initialize, $kingdom, $actor): void {
            if ($bootstrap) {
                $initialize->handle($kingdom->kingdomId, $actor->playerId);
            } else {
                app(ReconcileKingdomGovernancePolicy::class)->handle($actor->playerId, $kingdom->kingdomId);
            }
        };
        try {
            $run();
            self::fail('Operations failure must roll back the complete Workflow.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected policy provisioning failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        foreach ($before as $table => $rows) {
            self::assertEqualsCanonicalizing($rows, DB::table($table)->get()->map(static fn ($row): array => (array) $row)->all(), $table.' must be restored exactly.');
        }
        $run();
        self::assertSame(3, DB::table('kingdom_roles')->where('kingdom_id', $kingdom->kingdomId)->count());
        self::assertSame(1, DB::table('kingdom_role_assignments')->where('kingdom_id', $kingdom->kingdomId)->count());
        $adminRole = DB::table('kingdom_roles')->where('kingdom_id', $kingdom->kingdomId)->where('key', DefaultKingdomRole::Administrator->value)->value('id');
        self::assertNotNull($adminRole);
        self::assertSame(5, DB::table('kingdom_role_permissions')->join('permissions', 'permissions.id', '=', 'permission_id')
            ->where('kingdom_role_id', $adminRole)->where('owner_key', 'operations')->count());
    }

    public function test_actor_revocation_cannot_overtake_reconciliation_and_revoked_retry_fails(): void
    {
        $factory = app(ScenarioFactory::class);
        $kingdom = $factory->kingdom(61596);
        $actor = $factory->unclaimedPlayer(61596);
        $otherActor = $factory->unclaimedPlayer(61596);
        $roles = app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $actor->playerId);
        app(AssignKingdomRole::class)->handle($actor->playerId, $kingdom->kingdomId, $otherActor->playerId, $roles->administratorRoleId);
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.policy_competitor', [...DB::connection()->getConfig(), 'name' => 'policy_competitor']);
        $other = DB::connection('policy_competitor');
        $other->statement("SET lock_timeout = '150ms'");
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $otherActor, $kingdom, $roles, &$attempted): void {
            if (! $attempted && $query->connectionName === $primary && str_starts_with($query->sql, 'insert into "permissions"') && in_array('operations', $query->bindings, true)) {
                $attempted = true;
                DB::setDefaultConnection('policy_competitor');
                try {
                    try {
                        app(RemoveKingdomRole::class)->handle($otherActor->playerId, $kingdom->kingdomId, $roles->assignmentId, 'Verified authority withdrawal.');
                        self::fail('Revocation must wait for the complete policy composition.');
                    } catch (QueryException $exception) {
                        self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                    }
                } finally {
                    DB::setDefaultConnection($primary);
                }
            }
        });
        try {
            app(ReconcileKingdomGovernancePolicy::class)->handle($actor->playerId, $kingdom->kingdomId);
            self::assertTrue($attempted);
            app(RemoveKingdomRole::class)->handle($otherActor->playerId, $kingdom->kingdomId, $roles->assignmentId, 'Verified authority withdrawal.');
            $this->expectException(AuthorizationException::class);
            app(ReconcileKingdomGovernancePolicy::class)->handle($actor->playerId, $kingdom->kingdomId);
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('policy_competitor');
        }
    }
}
