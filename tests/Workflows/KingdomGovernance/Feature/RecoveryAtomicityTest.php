<?php

declare(strict_types=1);

namespace Tests\Workflows\KingdomGovernance\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TotpService;
use App\Contexts\Platform\Administration\Actions\AuthorizePlatformOperatorWrite;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\Workflows\KingdomGovernance\Actions\RecoverKingdomAdministrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class RecoveryAtomicityTest extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{bool}> */
    public static function orders(): iterable
    {
        yield 'recovery before revocation' => [true];
        yield 'revocation before recovery' => [false];
    }

    #[DataProvider('orders')]
    public function test_operator_grant_is_held_until_recovery_commits(bool $recoveryFirst): void
    {
        $factory = app(ScenarioFactory::class);
        $a = $factory->account();
        $b = $factory->account();
        $accounts = app(AccountIdentityQuery::class);
        $actor = $accounts->require($a->userId);
        $revoker = $accounts->require($b->userId);
        $manage = app(ManagePlatformAdministrator::class);
        $grant = $manage->grant($a->userId);
        $manage->grant($b->userId, $actor);
        $kingdom = $factory->kingdom(61581);
        $target = $factory->unclaimedPlayer(61581);
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.recovery_competitor', [...DB::connection()->getConfig(), 'name' => 'recovery_competitor']);
        $other = DB::connection('recovery_competitor');
        $other->statement("SET lock_timeout = '150ms'");
        DB::statement("SET lock_timeout = '150ms'");
        $attempted = false;
        if ($recoveryFirst) {
            DB::listen(static function (QueryExecuted $query) use ($primary, $manage, $revoker, $grant, &$attempted): void {
                if ($attempted || $query->connectionName !== $primary || ! str_contains($query->sql, 'from "platform_administrators"') || ! str_contains($query->sql, 'for update')) {
                    return;
                }
                $attempted = true;
                DB::setDefaultConnection('recovery_competitor');
                try {
                    try {
                        $manage->revoke($revoker, $grant);
                        self::fail('Revocation must wait for the complete recovery transaction.');
                    } catch (QueryException $exception) {
                        self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                    }
                } finally {
                    DB::setDefaultConnection($primary);
                }
            });
        }
        try {
            if ($recoveryFirst) {
                $result = app(RecoverKingdomAdministrator::class)->handle($actor, $kingdom->kingdomId, $target->playerId, 'Operator recovery incident.', true);
                self::assertTrue($attempted);
                self::assertTrue(DB::table('kingdom_role_assignments')->where('id', $result->assignmentId)->exists());
                self::assertSame(1, DB::table('audit_events')->where('event', 'kingdom.administrator_recovered')->where('subject_id', $result->assignmentId)->count());
                $manage->revoke($revoker, $grant);
            } else {
                $other->beginTransaction();
                $other->table('platform_administrators')->where('id', $grant)->update(['revoked_at' => now()]);
                try {
                    app(RecoverKingdomAdministrator::class)->handle($actor, $kingdom->kingdomId, $target->playerId, 'Operator recovery incident.', true);
                    self::fail('Recovery must not pass an uncommitted revocation.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                self::assertSame(0, DB::table('kingdom_role_assignments')->count());
                $other->commit();
            }
            try {
                app(RecoverKingdomAdministrator::class)->handle($actor, $kingdom->kingdomId, $target->playerId, 'Operator recovery incident.', true);
                self::fail('A stale account identity cannot restore revoked operator authority.');
            } catch (AuthorizationException) {
                self::assertSame($recoveryFirst ? 1 : 0, DB::table('kingdom_role_assignments')->count());
            }
        } finally {
            DB::setDefaultConnection($primary);
            if ($other->transactionLevel() > 0) {
                $other->rollBack();
            }
            DB::statement('SET lock_timeout = DEFAULT');
            DB::purge('recovery_competitor');
        }
    }

    /** @return iterable<string,array{string}> */
    public static function failurePoints(): iterable
    {
        yield 'Governance audit' => ['audit'];
        yield 'Governance outbox' => ['outbox'];
        yield 'Operations permission writes after Governance repair' => ['operations'];
    }

    #[DataProvider('failurePoints')]
    public function test_late_failure_rolls_back_all_owners_and_retry_is_idempotent(string $point): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        app(ManagePlatformAdministrator::class)->grant($account->userId);
        $actor = app(AccountIdentityQuery::class)->require($account->userId);
        $kingdom = $factory->kingdom(61582);
        $target = $factory->unclaimedPlayer(61582);
        $before = [];
        foreach (['kingdom_roles', 'kingdom_role_assignments', 'kingdom_role_permissions', 'permissions', 'audit_events', 'outbox_messages'] as $table) {
            $before[$table] = DB::table($table)->count();
        }
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use ($point, &$failed): void {
            $recoveryEvent = in_array('kingdom.administrator_recovered', $query->bindings, true);
            $matches = match ($point) {
                'audit' => str_starts_with($query->sql, 'insert into "audit_events"') && $recoveryEvent,
                'outbox' => str_starts_with($query->sql, 'insert into "outbox_messages"') && $recoveryEvent,
                default => str_starts_with($query->sql, 'insert into "permissions"') && in_array('operations', $query->bindings, true),
            };
            if (! $failed && $matches) {
                $failed = true;
                throw new RuntimeException('Injected recovery failure.');
            }
        });
        $action = app(RecoverKingdomAdministrator::class);
        try {
            $action->handle($actor, $kingdom->kingdomId, $target->playerId, 'Verified operator incident.', true);
            self::fail('Injected late owner failure must abort recovery.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected recovery failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        foreach ($before as $table => $count) {
            self::assertSame($count, DB::table($table)->count(), $table.' must roll back.');
        }
        $result = $action->handle($actor, $kingdom->kingdomId, $target->playerId, 'Verified operator incident.', true);
        $again = $action->handle($actor, $kingdom->kingdomId, $target->playerId, 'Verified operator incident.', true);
        self::assertSame($result->assignmentId, $again->assignmentId);
        self::assertSame(1, DB::table('kingdom_role_assignments')->count());
        self::assertSame(1, DB::table('audit_events')->where('event', 'kingdom.administrator_recovered')->count());
        self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'kingdom.administrator_recovered')->count());
        self::assertTrue(DB::table('kingdom_role_permissions')->join('permissions', 'permissions.id', '=', 'permission_id')
            ->where('kingdom_role_id', $result->administratorRoleId)->where('owner_key', 'operations')->exists());
    }

    public function test_http_rechecks_revoked_grant_after_middleware_admission(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $grant = app(ManagePlatformAdministrator::class)->grant($account->userId);
        $kingdom = $factory->kingdom(61583);
        $target = $factory->unclaimedPlayer(61583);
        $user = User::query()->findOrFail($account->userId);
        $user->forceFill(['email_verified_at' => now(), 'two_factor_secret' => app(TotpService::class)->generateSecret(), 'two_factor_confirmed_at' => now()])->save();
        $revoked = false;
        DB::listen(static function (QueryExecuted $query) use ($grant, &$revoked): void {
            if (! $revoked && str_starts_with($query->sql, 'select exists(') && str_contains($query->sql, '"platform_administrators"')) {
                $revoked = true;
                DB::table('platform_administrators')->where('id', $grant)->update(['revoked_at' => now()]);
            }
        });
        $this->actingAs($user)->withSession(['accounts.recent_authentication_at' => now()->timestamp])
            ->post('/platform/kingdom-governance-recovery', ['kingdom_id' => $kingdom->kingdomId,
                'player_id' => $target->playerId, 'reason' => 'Confirmed recovery incident.', 'replace_existing' => true])->assertForbidden();
        self::assertTrue($revoked);
        self::assertSame(0, DB::table('kingdom_roles')->count());
        self::assertSame(0, DB::table('kingdom_role_assignments')->count());
    }

    public function test_platform_owner_barrier_requires_its_workflow_transaction(): void
    {
        $account = app(ScenarioFactory::class)->account();
        app(ManagePlatformAdministrator::class)->grant($account->userId);
        $this->expectException(\LogicException::class);
        app(AuthorizePlatformOperatorWrite::class)
            ->handle(app(AccountIdentityQuery::class)->require($account->userId));
    }

    /** @return iterable<string,array{string,bool}> */
    public static function scopeOrders(): iterable
    {
        foreach (['kingdoms', 'players'] as $table) {
            yield $table.' recovery first' => [$table, true];
            yield $table.' scope change first' => [$table, false];
        }
    }

    #[DataProvider('scopeOrders')]
    public function test_current_scope_change_cannot_cross_recovery(string $table, bool $recoveryFirst): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        app(ManagePlatformAdministrator::class)->grant($account->userId);
        $actor = app(AccountIdentityQuery::class)->require($account->userId);
        $kingdom = $factory->kingdom(61589);
        $foreign = $factory->kingdom(61590);
        $target = $factory->unclaimedPlayer(61589);
        $key = $table === 'kingdoms' ? $kingdom->kingdomId : $target->playerId;
        $change = $table === 'kingdoms' ? ['status' => 'archived'] : ['current_kingdom_id' => $foreign->kingdomId];
        config()->set('database.connections.recovery_scope', [...DB::connection()->getConfig(), 'name' => 'recovery_scope']);
        $other = DB::connection('recovery_scope');
        $other->statement("SET lock_timeout = '150ms'");
        DB::statement("SET lock_timeout = '150ms'");
        $primary = DB::getDefaultConnection();
        $attempted = false;
        if ($recoveryFirst) {
            DB::listen(static function (QueryExecuted $query) use ($table, $key, $change, $primary, $other, &$attempted): void {
                if (! $attempted && $query->connectionName === $primary && str_contains($query->sql, 'from "'.$table.'"') && str_contains($query->sql, 'for update')) {
                    $attempted = true;
                    try {
                        $other->table($table)->where('id', $key)->update($change);
                        self::fail('Scope change must wait until recovery commits.');
                    } catch (QueryException $exception) {
                        self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                    }
                }
            });
        }
        try {
            $action = app(RecoverKingdomAdministrator::class);
            if ($recoveryFirst) {
                $result = $action->handle($actor, $kingdom->kingdomId, $target->playerId, 'Confirmed recovery scope.', true);
                self::assertTrue($attempted);
                self::assertTrue(DB::table('kingdom_role_assignments')->where('id', $result->assignmentId)->exists());
            } else {
                $other->beginTransaction();
                $other->table($table)->where('id', $key)->update($change);
                try {
                    $action->handle($actor, $kingdom->kingdomId, $target->playerId, 'Confirmed recovery scope.', true);
                    self::fail('Recovery must wait for the current scope owner.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                $other->commit();
                try {
                    $action->handle($actor, $kingdom->kingdomId, $target->playerId, 'Confirmed recovery scope.', true);
                    self::fail('Committed invalid scope must reject the retry.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey($table === 'kingdoms' ? 'kingdom_id' : 'player_id', $exception->errors());
                }
                self::assertSame(0, DB::table('kingdom_roles')->count());
                self::assertSame(0, DB::table('kingdom_role_assignments')->count());
            }
        } finally {
            if ($other->transactionLevel() > 0) {
                $other->rollBack();
            }
            DB::statement('SET lock_timeout = DEFAULT');
            DB::purge('recovery_scope');
        }
    }
}
