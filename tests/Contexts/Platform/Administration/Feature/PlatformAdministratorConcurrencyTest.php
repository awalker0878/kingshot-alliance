<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\Administration\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TotpService;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\Contexts\Platform\DataGovernance\Actions\ProcessAccountDeletionRequests;
use App\Contexts\Platform\DataGovernance\Actions\RequestAccountDeletion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class PlatformAdministratorConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    public static function orders(): iterable
    {
        yield 'grant first' => [true];
        yield 'competing operation first' => [false];
    }

    #[DataProvider('orders')]
    public function test_crossed_grant_and_revocation_serialize_before_grant_rows(bool $grantFirst): void
    {
        $factory = app(ScenarioFactory::class);
        $a = $factory->account();
        $b = $factory->account();
        $accounts = app(AccountIdentityQuery::class);
        $actorA = $accounts->require($a->userId);
        $actorB = $accounts->require($b->userId);
        $manage = app(ManagePlatformAdministrator::class);
        $manage->grant($a->userId);
        $grantB = $manage->grant($b->userId, $actorA);
        $grant = static fn () => $manage->grant($a->userId, $actorB);
        $revoke = static fn () => $manage->revoke($actorA, $grantB);
        $primary = DB::getDefaultConnection();
        $this->competitor();
        $attempted = false;
        $competingGrantRow = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $grantFirst, $grant, $revoke, &$attempted, &$competingGrantRow): void {
            if ($query->connectionName === 'administrator_competitor' && str_contains($query->sql, 'from "platform_administrators"') && str_contains($query->sql, 'for update')) {
                $competingGrantRow = true;
            }
            if ($attempted || $query->connectionName !== $primary || ! str_contains($query->sql, 'pg_advisory_xact_lock')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('administrator_competitor');
            try {
                try {
                    $grantFirst ? $revoke() : $grant();
                    self::fail('The competing catalogue command must wait before row acquisition.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                    self::assertFalse($competingGrantRow);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $grantFirst ? $grant() : $revoke();
            self::assertTrue($attempted);
            if ($grantFirst) {
                $revoke();
            }
            try {
                $grant();
                self::fail('Revoked authority must not grant on retry.');
            } catch (AuthorizationException) {
                self::assertSame(1, DB::table('platform_administrators')->whereNull('revoked_at')->count());
            }
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('administrator_competitor');
        }
    }

    #[DataProvider('orders')]
    public function test_first_bootstrap_and_absent_target_grants_are_serialized(bool $bootstrap): void
    {
        $factory = app(ScenarioFactory::class);
        $a = $factory->account();
        $b = $factory->account();
        $target = $factory->account();
        $manage = app(ManagePlatformAdministrator::class);
        $accounts = app(AccountIdentityQuery::class);
        $actorA = $bootstrap ? null : $accounts->require($a->userId);
        $actorB = $bootstrap ? null : $accounts->require($b->userId);
        if (! $bootstrap) {
            $manage->grant($a->userId);
            $manage->grant($b->userId, $actorA);
        }
        $primary = DB::getDefaultConnection();
        $this->competitor();
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $target, $actorB, $manage, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_contains($query->sql, 'pg_advisory_xact_lock')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('administrator_competitor');
            try {
                try {
                    $manage->grant($target->userId, $actorB);
                    self::fail('An absent grant must still have a serialization barrier.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $id = $manage->grant($target->userId, $actorA);
            self::assertTrue($attempted);
            $auditCount = DB::table('audit_events')->count();
            if ($bootstrap) {
                try {
                    $manage->grant($target->userId);
                    self::fail('Bootstrap must reject once active access exists.');
                } catch (InvalidArgumentException) {
                    self::assertSame(1, DB::table('platform_administrators')->count());
                }
            } else {
                self::assertSame($id, $manage->grant($target->userId, $actorB));
                self::assertSame(3, DB::table('platform_administrators')->count());
            }
            self::assertSame($auditCount, DB::table('audit_events')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('administrator_competitor');
        }
    }

    #[DataProvider('orders')]
    public function test_grant_and_account_finalization_share_the_current_target_barrier(bool $grantFirst): void
    {
        $factory = app(ScenarioFactory::class);
        $admin = $factory->account();
        $target = $factory->account();
        $manage = app(ManagePlatformAdministrator::class);
        $manage->grant($admin->userId);
        $actor = app(AccountIdentityQuery::class)->require($admin->userId);
        $request = app(RequestAccountDeletion::class)->handle($target->userId);
        DB::table('account_deletion_requests')->where('id', $request)->update(['eligible_at' => now()->subMinute()]);
        $grant = static fn () => $manage->grant($target->userId, $actor);
        $delete = static fn () => app(ProcessAccountDeletionRequests::class)->handle();
        $primary = DB::getDefaultConnection();
        $this->competitor();
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $grantFirst, $target, $grant, $delete, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "users"')
                || ! str_contains($query->sql, 'for update') || ! in_array($target->userId, $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('administrator_competitor');
            try {
                try {
                    $grantFirst ? $delete() : $grant();
                    self::fail('The competing command must respect the target account barrier.');
                } catch (QueryException|InvalidArgumentException $exception) {
                    $database = $exception instanceof QueryException ? $exception : $exception->getPrevious();
                    self::assertInstanceOf(QueryException::class, $database);
                    self::assertSame('55P03', $database->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $grantFirst ? $grant() : $delete();
            self::assertTrue($attempted);
            if ($grantFirst) {
                self::assertSame(0, $delete());
                self::assertSame('blocked', DB::table('account_deletion_requests')->where('id', $request)->value('status'));
                self::assertNull(DB::table('users')->where('id', $target->userId)->value('anonymized_at'));
            } else {
                self::assertNotNull(DB::table('users')->where('id', $target->userId)->value('anonymized_at'));
                try {
                    $grant();
                    self::fail('A finalized target cannot receive access.');
                } catch (InvalidArgumentException $exception) {
                    self::assertSame('The target account is no longer active.', $exception->getMessage());
                    self::assertFalse(DB::table('platform_administrators')->where('user_id', $target->userId)->exists());
                }
            }
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('administrator_competitor');
        }
    }

    public function test_late_audit_failure_rolls_back_access_and_releases_bootstrap_coordination(): void
    {
        $target = app(ScenarioFactory::class)->account();
        $before = DB::table('audit_events')->count();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "audit_events"')) {
                $failed = true;
                throw new RuntimeException('Injected grant audit failure.');
            }
        });
        try {
            app(ManagePlatformAdministrator::class)->grant($target->userId);
            self::fail('The late audit write must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected grant audit failure.', $exception->getMessage());
        }
        self::assertSame(0, DB::table('platform_administrators')->count());
        self::assertSame($before, DB::table('audit_events')->count());
        app(ManagePlatformAdministrator::class)->grant($target->userId);
        self::assertSame(1, DB::table('platform_administrators')->count());
    }

    public function test_http_contention_is_a_field_error_and_current_grant_authority_is_rechecked(): void
    {
        $factory = app(ScenarioFactory::class);
        $admin = $factory->account();
        $target = $factory->account();
        $grantId = app(ManagePlatformAdministrator::class)->grant($admin->userId);
        $user = User::query()->findOrFail($admin->userId);
        $user->forceFill(['email_verified_at' => now(), 'two_factor_secret' => app(TotpService::class)->generateSecret(), 'two_factor_confirmed_at' => now()])->save();
        $this->competitor();
        $other = DB::connection('administrator_competitor');
        try {
            $other->beginTransaction();
            $other->table('users')->where('id', $target->userId)->lockForUpdate()->first();
            $this->actingAs($user)->withSession(['accounts.recent_authentication_at' => now()->timestamp])
                ->postJson('/platform/administrators', ['email' => $target->email])->assertUnprocessable()->assertJsonValidationErrors('email');
            self::assertFalse(DB::table('platform_administrators')->where('user_id', $target->userId)->exists());
            $other->rollBack();
            $this->postJson('/platform/administrators', ['email' => $target->email])->assertRedirect();
            self::assertTrue(DB::table('platform_administrators')->where('user_id', $target->userId)->exists());
            DB::table('platform_administrators')->where('id', $grantId)->update(['revoked_at' => now()]);
            $this->postJson('/platform/administrators', ['email' => $target->email])->assertForbidden();
            $this->artisan('platform:admin:grant', ['email' => $target->email])
                ->expectsOutputToContain('Bootstrap grants are allowed only when no active Platform Administrator exists.')
                ->assertFailed();
        } finally {
            if ($other->transactionLevel() > 0) {
                $other->rollBack();
            }
            DB::purge('administrator_competitor');
        }
    }

    private function competitor(): void
    {
        config()->set('database.connections.administrator_competitor', [...DB::connection()->getConfig(), 'name' => 'administrator_competitor']);
        DB::connection('administrator_competitor')->statement("SET lock_timeout = '150ms'");
    }
}
