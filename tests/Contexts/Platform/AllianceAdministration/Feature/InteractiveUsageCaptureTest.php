<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\AllianceAdministration\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TotpService;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\Contexts\Platform\AllianceAdministration\Actions\CaptureAllianceUsage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class InteractiveUsageCaptureTest extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{bool}> */
    public static function orders(): iterable
    {
        yield 'capture before revocation' => [true];
        yield 'revocation before capture' => [false];
    }

    #[DataProvider('orders')]
    public function test_operator_grant_is_held_until_capture_commits(bool $captureFirst): void
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
        $alliance = $factory->alliance($factory->player($a->userId));
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.usage_competitor', [...DB::connection()->getConfig(), 'name' => 'usage_competitor']);
        $other = DB::connection('usage_competitor');
        $other->statement("SET lock_timeout = '150ms'");
        DB::statement("SET lock_timeout = '150ms'");
        $attempted = false;
        if ($captureFirst) {
            DB::listen(static function (QueryExecuted $query) use ($primary, $manage, $revoker, $grant, &$attempted): void {
                if ($attempted || $query->connectionName !== $primary || ! str_contains($query->sql, 'from "platform_administrators"') || ! str_contains($query->sql, 'for update')) {
                    return;
                }
                $attempted = true;
                DB::setDefaultConnection('usage_competitor');
                try {
                    try {
                        $manage->revoke($revoker, $grant);
                        self::fail('Revocation must wait for the complete snapshot transaction.');
                    } catch (QueryException $exception) {
                        self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                    }
                } finally {
                    DB::setDefaultConnection($primary);
                }
            });
        }
        try {
            if ($captureFirst) {
                $id = app(CaptureAllianceUsage::class)->handle($actor, $alliance->allianceId);
                self::assertTrue($attempted);
                self::assertTrue(DB::table('alliance_usage_snapshots')->where('id', $id)->exists());
                self::assertSame(1, DB::table('audit_events')->where('event', 'platform.alliance.usage-captured')->where('subject_id', $id)->count());
                $manage->revoke($revoker, $grant);
            } else {
                $other->beginTransaction();
                $other->table('platform_administrators')->where('id', $grant)->update(['revoked_at' => now()]);
                try {
                    app(CaptureAllianceUsage::class)->handle($actor, $alliance->allianceId);
                    self::fail('Capture must not pass an uncommitted revocation.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                self::assertSame(0, DB::table('alliance_usage_snapshots')->count());
                $other->commit();
            }
            try {
                app(CaptureAllianceUsage::class)->handle($actor, $alliance->allianceId);
                self::fail('A stale account identity cannot restore revoked operator authority.');
            } catch (AuthorizationException) {
                self::assertSame($captureFirst ? 1 : 0, DB::table('alliance_usage_snapshots')->count());
            }
        } finally {
            DB::setDefaultConnection($primary);
            if ($other->transactionLevel() > 0) {
                $other->rollBack();
            }
            DB::statement('SET lock_timeout = DEFAULT');
            DB::purge('usage_competitor');
        }
    }

    public function test_late_audit_failure_rolls_back_snapshot_and_retry_captures_once(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        app(ManagePlatformAdministrator::class)->grant($account->userId);
        $alliance = $factory->alliance($factory->player($account->userId));
        $actor = app(AccountIdentityQuery::class)->require($account->userId);
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "audit_events"')) {
                $failed = true;
                throw new RuntimeException('Injected usage audit failure.');
            }
        });
        try {
            app(CaptureAllianceUsage::class)->handle($actor, $alliance->allianceId);
            self::fail('The injected audit failure must abort capture.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected usage audit failure.', $exception->getMessage());
        }
        self::assertSame(0, DB::table('alliance_usage_snapshots')->count());
        self::assertSame(0, DB::table('audit_events')->where('event', 'platform.alliance.usage-captured')->count());
        $id = app(CaptureAllianceUsage::class)->handle($actor, $alliance->allianceId);
        self::assertSame(1, DB::table('alliance_usage_snapshots')->count());
        self::assertSame(1, (int) DB::table('alliance_usage_snapshots')->where('id', $id)->value('active_members'));
    }

    public function test_http_capture_rechecks_authority_after_middleware_admission(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $grant = app(ManagePlatformAdministrator::class)->grant($account->userId);
        $alliance = $factory->alliance($factory->player($account->userId));
        $user = User::query()->findOrFail($account->userId);
        $user->forceFill(['email_verified_at' => now(), 'two_factor_secret' => app(TotpService::class)->generateSecret(), 'two_factor_confirmed_at' => now()])->save();
        $url = '/platform/alliances/'.$alliance->allianceId.'/usage';
        $this->actingAs($user)->withSession(['accounts.recent_authentication_at' => now()->timestamp])->post($url)->assertRedirect();
        self::assertSame(1, DB::table('alliance_usage_snapshots')->count());
        $revoked = false;
        DB::listen(static function (QueryExecuted $query) use ($grant, &$revoked): void {
            if (! $revoked && str_starts_with($query->sql, 'select exists(') && str_contains($query->sql, '"platform_administrators"')) {
                $revoked = true;
                DB::table('platform_administrators')->where('id', $grant)->update(['revoked_at' => now()]);
            }
        });
        $this->post($url)->assertForbidden();
        self::assertTrue($revoked);
        self::assertSame(1, DB::table('alliance_usage_snapshots')->count());
        self::assertSame(1, DB::table('audit_events')->where('event', 'platform.alliance.usage-captured')->count());
    }
}
