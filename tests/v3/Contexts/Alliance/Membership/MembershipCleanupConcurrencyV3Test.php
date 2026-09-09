<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Membership;

use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Actions\RemovePlayersFromAlliances;
use App\Contexts\Alliance\Membership\Actions\TransferAllianceLeadership;
use App\Contexts\Alliance\Membership\Actions\UpdateMembershipStatus;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class MembershipCleanupConcurrencyV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{bool,bool}> */
    public static function commitOrders(): iterable
    {
        foreach ([true, false] as $active) {
            yield ($active ? 'active' : 'historical').' cleanup first' => [true, $active];
            yield ($active ? 'active' : 'historical').' administration first' => [false, $active];
        }
    }

    #[DataProvider('commitOrders')]
    public function test_cleanup_locks_alliance_scopes_before_memberships_in_both_commit_orders(bool $cleanupFirst, bool $active): void
    {
        [$owner, $player, $membership] = $this->membership($active);
        $cleanup = static fn () => app(RemovePlayersFromAlliances::class)->handle([$player]);
        $activate = static fn () => app(UpdateMembershipStatus::class)->handle((string) $membership->alliance_id, $owner->playerId, (string) $membership->id, MembershipStatus::Active);
        $primary = $this->competitor('cleanup_competitor');
        $attempted = false;
        $membershipLocks = [];
        DB::listen(static function (QueryExecuted $query) use ($primary, $cleanupFirst, $cleanup, $activate, &$attempted, &$membershipLocks): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, '"alliance_memberships"') && str_contains($query->sql, 'for update')) {
                $membershipLocks[] = $query->connectionName;
            }
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "alliances"')
                || ! str_contains($query->sql, $cleanupFirst ? 'for share' : 'for update')) {
                return;
            }
            $attempted = true;
            self::assertSame([], $membershipLocks, 'The scope must be acquired before any membership lock.');
            DB::setDefaultConnection('cleanup_competitor');
            try {
                try {
                    $cleanupFirst ? $activate() : $cleanup();
                    self::fail('The competing writer must wait at the Alliance scope.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                self::assertNotContains('cleanup_competitor', $membershipLocks, 'A blocked cleanup must not hold a membership while waiting for its Alliance.');
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $cleanupFirst ? $cleanup() : $activate();
            self::assertTrue($attempted);
            DB::setDefaultConnection('cleanup_competitor');
            $cleanupFirst ? $activate() : $cleanup();
            self::assertSame($cleanupFirst ? MembershipStatus::Active : MembershipStatus::Left, $membership->fresh()?->status);
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('cleanup_competitor');
        }
    }

    public function test_new_scope_after_discovery_rejects_cleanup_without_losing_either_membership(): void
    {
        [, $player, $membership] = $this->membership(true);
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $newOwner = $factory->player($account->userId, 59263);
        $primary = $this->competitor('new_scope');
        $created = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $account, $newOwner, &$created): void {
            if ($created || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "alliances"') || ! str_contains($query->sql, 'for share')) {
                return;
            }
            $created = true;
            DB::setDefaultConnection('new_scope');
            try {
                app(CreateAlliance::class)->handle($account->userId, $newOwner->playerId, 'New Scope', 'new-scope');
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            try {
                app(RemovePlayersFromAlliances::class)->handle([$player, $newOwner]);
                self::fail('New membership scopes must be revalidated before cleanup writes.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('membership', $exception->errors());
            }
            self::assertTrue($created);
            self::assertSame(MembershipStatus::Active, $membership->fresh()?->status);
            self::assertSame(AllianceRank::R5, AllianceMembership::query()->where('player_id', $newOwner->playerId)->firstOrFail()->rank);
            self::assertSame(0, DB::table('audit_events')->where('event', 'membership.left')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('new_scope');
        }
    }

    public function test_leadership_transfer_before_scope_lock_preserves_current_r5(): void
    {
        [$owner, $player, $membership] = $this->membership(true);
        $primary = $this->competitor('new_leader');
        $transferred = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $owner, $player, $membership, &$transferred): void {
            if ($transferred || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select "alliance_id" from "alliance_memberships"')) {
                return;
            }
            $transferred = true;
            DB::setDefaultConnection('new_leader');
            try {
                app(TransferAllianceLeadership::class)->handle((string) $membership->alliance_id, $owner->playerId, $player->playerId);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            try {
                app(RemovePlayersFromAlliances::class)->handle([$player]);
                self::fail('Cleanup must honor the current leadership rank.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('membership', $exception->errors());
            }
            self::assertTrue($transferred);
            self::assertSame(AllianceRank::R5, $membership->fresh()?->rank);
            self::assertSame(MembershipStatus::Active, $membership->fresh()?->status);
            self::assertSame(0, DB::table('audit_events')->where('event', 'membership.left')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('new_leader');
        }
    }

    public function test_multiple_alliances_are_scoped_and_repeated_cleanup_has_no_extra_events(): void
    {
        [, $first, $firstMembership] = $this->membership(true);
        [, $second, $secondMembership] = $this->membership(true);
        [, , $untouched] = $this->membership(true);
        app(RemovePlayersFromAlliances::class)->handle([$second, $first, $first]);
        self::assertSame(MembershipStatus::Left, $firstMembership->fresh()?->status);
        self::assertSame(MembershipStatus::Left, $secondMembership->fresh()?->status);
        self::assertSame(MembershipStatus::Active, $untouched->fresh()?->status);
        self::assertSame(2, DB::table('audit_events')->where('event', 'membership.left')->count());
        self::assertSame(2, DB::table('outbox_messages')->where('event_type', 'membership.left')->count());
        $before = $this->state();
        app(RemovePlayersFromAlliances::class)->handle([$first, $second]);
        self::assertSame($before, $this->state());
    }

    public function test_late_outbox_failure_rolls_back_all_scopes_and_role_detachment(): void
    {
        [, $first, $membership] = $this->membership(true);
        [, $second] = $this->membership(true);
        $roleId = DB::table('roles')->where('alliance_id', $membership->alliance_id)->value('id');
        self::assertNotNull($roleId);
        $membership->roles()->attach($roleId, ['alliance_id' => $membership->alliance_id]);
        $before = $this->state();
        $events = 0;
        DB::listen(static function (QueryExecuted $query) use (&$events): void {
            if (str_starts_with($query->sql, 'insert into "outbox_messages"') && in_array('membership.left', $query->bindings, true) && ++$events === 2) {
                throw new RuntimeException('Injected second cleanup outbox failure.');
            }
        });
        try {
            app(RemovePlayersFromAlliances::class)->handle([$first, $second]);
            self::fail('A late failure must roll back every cleanup scope.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected second cleanup outbox failure.', $exception->getMessage());
        }
        self::assertSame(2, $events);
        self::assertSame($before, $this->state());
    }

    /** @return array{PlayerReference,PlayerReference,AllianceMembership} */
    private function membership(bool $active): array
    {
        $factory = app(ScenarioFactory::class);
        $ownerAccount = $factory->account();
        $owner = $factory->player($ownerAccount->userId, 59263);
        $alliance = $factory->alliance($owner);
        $account = $factory->account();
        $player = $factory->player($account->userId, 59263);
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId, 'player_id' => $player->playerId,
            'status' => $active ? MembershipStatus::Active : MembershipStatus::Suspended,
            'rank' => AllianceRank::R1, 'joined_at' => now(),
        ]);

        return [$owner, $player, $membership];
    }

    private function competitor(string $name): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.'.$name, array_replace(DB::connection()->getConfig(), ['name' => $name]));
        DB::connection($name)->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        return [
            'memberships' => DB::table('alliance_memberships')->orderBy('id')->get()->toJson(),
            'roles' => DB::table('membership_roles')->orderBy('membership_id')->orderBy('role_id')->get()->toJson(),
            'audit' => DB::table('audit_events')->orderBy('id')->get()->toJson(),
            'outbox' => DB::table('outbox_messages')->orderBy('id')->get()->toJson(),
        ];
    }
}
