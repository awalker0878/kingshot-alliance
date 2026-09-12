<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\Governance\Feature;

use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\CreateKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\ReconcileKingdomRolePermissions;
use App\Contexts\GameWorld\Governance\Actions\UpdateKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingdomRoleAuthorityConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{bool,bool}> */
    public static function orders(): iterable
    {
        yield 'role update after admission' => [false, false];
        yield 'role update before admission' => [false, true];
        yield 'permission owner reconciliation after admission' => [true, false];
        yield 'permission owner reconciliation before admission' => [true, true];
    }

    #[DataProvider('orders')]
    public function test_delegation_holds_current_permissions_until_commit_and_rechecks_after_withdrawal(bool $reconcile, bool $withdrawFirst): void
    {
        $factory = app(ScenarioFactory::class);
        $admin = $factory->unclaimedPlayer(61621);
        $deputy = $factory->unclaimedPlayer(61621);
        $target = $factory->unclaimedPlayer(61621);
        app(BootstrapKingdomAdministrator::class)->handle($admin->kingdomId, $admin->playerId);
        $create = app(CreateKingdomRole::class);
        $role = $create->handle($admin->playerId, $admin->kingdomId, 'Deputy', null, [KingdomPermission::RoleManage->key(), OperationsPermission::EventKingdomManage->value]);
        $delegated = $create->handle($admin->playerId, $admin->kingdomId, 'Event management', null, [OperationsPermission::EventKingdomManage->value]);
        app(AssignKingdomRole::class)->handle($admin->playerId, $admin->kingdomId, $deputy->playerId, $role);
        $withdraw = static function () use ($admin, $role, $reconcile): void {
            if ($reconcile) {
                app(ReconcileKingdomRolePermissions::class)->handle($admin->kingdomId, 'operations', [$role => []]);
            } else {
                app(UpdateKingdomRole::class)->handle($admin->playerId, $admin->kingdomId, $role, 'Deputy', null, [KingdomPermission::RoleManage->key()]);
            }
        };
        $assign = static fn (): string => app(AssignKingdomRole::class)->handle($deputy->playerId, $admin->kingdomId, $target->playerId, $delegated);
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.role_competitor', [...DB::connection()->getConfig(), 'name' => 'role_competitor']);
        $other = DB::connection('role_competitor');
        $other->statement("SET lock_timeout = '150ms'");
        DB::connection($primary)->statement("SET lock_timeout = '150ms'");
        try {
            if ($withdrawFirst) {
                $other->beginTransaction();
                DB::setDefaultConnection('role_competitor');
                $withdraw();
                DB::setDefaultConnection($primary);
                try {
                    $assign();
                    self::fail('Admission must wait for the pending permission withdrawal.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                $other->commit();
                self::assertSame(0, DB::table('kingdom_role_assignments')->where('player_id', $target->playerId)->count());
            } else {
                $attempted = false;
                DB::listen(static function (QueryExecuted $query) use ($primary, $withdraw, &$attempted): void {
                    if (! $attempted && $query->connectionName === $primary && str_starts_with($query->sql, 'insert into "kingdom_role_assignments"')) {
                        $attempted = true;
                        DB::setDefaultConnection('role_competitor');
                        try {
                            try {
                                $withdraw();
                                self::fail('Permission withdrawal must wait for the admitted assignment.');
                            } catch (QueryException $exception) {
                                self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                            }
                        } finally {
                            DB::setDefaultConnection($primary);
                        }
                    }
                });
                $assign();
                self::assertTrue($attempted);
                self::assertSame(1, DB::table('kingdom_role_assignments')->where('player_id', $target->playerId)->count());
                $withdraw();
            }
            // This includes replay of an already-created assignment. Admission is current.
            $this->expectException(ValidationException::class);
            $assign();
        } finally {
            DB::setDefaultConnection($primary);
            while ($other->transactionLevel() > 0) {
                $other->rollBack();
            }
            DB::connection($primary)->statement("SET lock_timeout = '0'");
            DB::purge('role_competitor');
        }
    }
}
