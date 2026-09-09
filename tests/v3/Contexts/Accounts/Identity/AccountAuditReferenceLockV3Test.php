<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Identity;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Profile\Actions\UpdateProfile;
use App\Contexts\Alliance\Membership\Actions\UpsertRosterEntry;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Players\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Platform\DataGovernance\Actions\ProcessAccountDeletionRequests;
use App\Contexts\Platform\DataGovernance\Models\AccountDeletionRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AccountAuditReferenceLockV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{bool,bool}> */
    public static function cleanupOrders(): iterable
    {
        foreach ([false, true] as $failCleanup) {
            yield ($failCleanup ? 'rollback ' : '').'account barrier first' => [true, $failCleanup];
            yield ($failCleanup ? 'rollback ' : '').'Alliance writer first' => [false, $failCleanup];
        }
    }

    #[DataProvider('cleanupOrders')]
    public function test_player_actor_audits_and_account_cleanup_preserve_current_authority(bool $accountFirst, bool $failCleanup): void
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59276);
        $alliance = $factory->alliance($owner);
        $account = $factory->account();
        $manager = $factory->player($account->userId, 59276);
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId, 'player_id' => $manager->playerId,
            'rank' => AllianceRank::R4, 'status' => MembershipStatus::Active, 'joined_at' => now(),
        ]);
        $entry = $factory->roster($owner, $alliance, $factory->unclaimedPlayer(59276));
        $request = AccountDeletionRequest::query()->create([
            'user_id' => $account->userId, 'status' => 'pending', 'requested_at' => now()->subDay(), 'eligible_at' => now()->subMinute(),
        ]);
        $write = static fn () => app(UpsertRosterEntry::class)->handle($manager->playerId, $alliance->allianceId, ['name' => 'Committed officer observation'], $entry->rosterEntryId);
        $cleanup = static fn () => app(ProcessAccountDeletionRequests::class)->handle();
        $primary = $this->competitor();
        $attempted = false;
        $failed = false;
        $afterWrite = null;
        DB::listen(function (QueryExecuted $query) use ($primary, $account, $accountFirst, $failCleanup, $write, $cleanup, &$attempted, &$failed, &$afterWrite): void {
            if ($failCleanup && ! $failed && str_starts_with($query->sql, 'insert into "audit_events"') && in_array('account.anonymized', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected account cleanup audit failure.');
            }
            $barrier = $accountFirst
                ? str_starts_with($query->sql, 'select * from "users"') && str_contains($query->sql, 'for update') && in_array((string) $account->userId, array_map('strval', $query->bindings), true)
                : str_starts_with($query->sql, 'select * from "alliance_memberships"') && str_contains($query->sql, 'for update');
            if ($attempted || $query->connectionName !== $primary || ! $barrier) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('account_reference_writer');
            try {
                if ($accountFirst) {
                    // Alliance audits reference the Player actor, so the write can
                    // finish before cleanup takes its Alliance scope.
                    $write();
                    $afterWrite = $this->state();
                } else {
                    try {
                        $cleanup();
                        self::fail('Cleanup must wait for the officer membership mutation.');
                    } catch (QueryException $exception) {
                        self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                    }
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            if (! $accountFirst) {
                $write();
                $afterWrite = $this->state();
            }
            try {
                self::assertSame(1, $cleanup());
                self::assertFalse($failCleanup);
            } catch (RuntimeException $exception) {
                self::assertTrue($failCleanup);
                self::assertSame('Injected account cleanup audit failure.', $exception->getMessage());
            }
            self::assertTrue($attempted);
            self::assertSame($failCleanup, $failed);
            self::assertSame('Committed officer observation', AllianceRosterEntry::query()->findOrFail($entry->rosterEntryId)->observed_name);
            self::assertSame(1, DB::table('audit_events')->where('event', 'membership.roster_entry_updated')->where('actor_player_id', $manager->playerId)->whereNull('actor_user_id')->count());
            if ($failCleanup) {
                self::assertSame($afterWrite, $this->state());
                self::assertSame(MembershipStatus::Active, $membership->fresh()?->status);
                self::assertSame($account->userId, Player::query()->findOrFail($manager->playerId)->user_id);
            } else {
                self::assertSame('processed', $request->fresh()?->status);
                self::assertSame(MembershipStatus::Left, $membership->fresh()?->status);
                self::assertNull(Player::query()->findOrFail($manager->playerId)->user_id);
                $beforeRetry = $this->state();
                try {
                    $write();
                    self::fail('Finalized officer authority must remain revoked.');
                } catch (AuthorizationException) {
                    self::assertSame($beforeRetry, $this->state());
                }
                self::assertSame(0, $cleanup());
            }
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('account_reference_writer');
        }
    }

    /** @return iterable<string,array{bool}> */
    public static function barriers(): iterable
    {
        yield 'current account' => [false];
        yield 'active account' => [true];
    }

    #[DataProvider('barriers')]
    public function test_account_barriers_exclude_account_and_ownership_writers(bool $active): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $player = $factory->unclaimedPlayer(59276);
        $primary = $this->competitor();
        try {
            DB::transaction(static function () use ($active, $account, $player, $primary): void {
                $active
                    ? app(AccountIdentityQuery::class)->lockActive($account->userId)
                    : app(AccountIdentityQuery::class)->lockCurrent($account->userId);
                DB::setDefaultConnection('account_reference_writer');
                try {
                    foreach (['claim', 'profile'] as $operation) {
                        try {
                            $operation === 'claim'
                                ? app(ClaimPlayerAccount::class)->handle($player->playerId, $account->userId)
                                : app(UpdateProfile::class)->handle($account->userId, 'Blocked account edit', 'UTC');
                            self::fail('The lifecycle barrier must still exclude mutable account authority.');
                        } catch (QueryException $exception) {
                            self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                        }
                    }
                } finally {
                    DB::setDefaultConnection($primary);
                }
                self::assertSame(1, (int) DB::selectOne('select 1 as usable')->usable);
            });
            app(ClaimPlayerAccount::class)->handle($player->playerId, $account->userId);
            self::assertSame($account->userId, Player::query()->findOrFail($player->playerId)->user_id);
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('account_reference_writer');
        }
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.account_reference_writer', array_replace(DB::connection()->getConfig(), ['name' => 'account_reference_writer']));
        DB::connection('account_reference_writer')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        $state = ['accounts' => DB::table('users')->select(['id', 'name', 'email', 'anonymized_at'])->orderBy('id')->get()->toJson()];
        foreach (['players', 'player_identity_history', 'alliance_memberships', 'alliance_roster_entries', 'account_deletion_requests', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }
}
