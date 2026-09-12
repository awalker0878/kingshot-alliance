<?php

declare(strict_types=1);

namespace Tests\Contexts\Alliance\Membership\Integration\Concurrency;

use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Access\Actions\RemoveMembershipRole;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Membership\Actions\UpdateAllianceRank;
use App\Contexts\Alliance\Membership\Actions\UpdateMembershipStatus;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
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

final class ProtectedMembershipTargetConcurrencyV3Test extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{string}> */
    public static function operations(): iterable
    {
        foreach (['assign role', 'remove role', 'rank', 'suspend', 'remove membership'] as $operation) {
            yield $operation => [$operation];
        }
    }

    /** @return iterable<string,array{string,bool}> */
    public static function opposingOrders(): iterable
    {
        foreach (self::operations() as [$operation]) {
            yield $operation.' first officer' => [$operation, false];
            yield $operation.' second officer' => [$operation, true];
        }
    }

    #[DataProvider('opposingOrders')]
    public function test_protected_target_writers_serialize_before_opposing_actor_membership(string $operation, bool $reverse): void
    {
        [, $allianceId, $first, $second, $roleId] = $this->fixture($operation);
        [$actor, $other] = $reverse ? [$second, $first] : [$first, $second];
        $write = fn () => $this->write($operation, (string) $actor->player_id, $allianceId, (string) $other->id, $roleId);
        $otherWrite = fn () => $operation === 'rank'
            ? app(UpdateAllianceRank::class)->handle($allianceId, (string) $other->player_id, (string) $actor->id, AllianceRank::R4)
            : $this->write($operation, (string) $other->player_id, $allianceId, (string) $actor->id, $roleId);
        $primary = $this->competitor();
        $attempted = false;
        $competingMembershipLocks = 0;
        $before = $this->state();
        DB::listen(static function (QueryExecuted $query) use ($primary, $actor, $otherWrite, &$attempted, &$competingMembershipLocks): void {
            if ($query->connectionName === 'membership_target_writer' && str_starts_with($query->sql, 'select * from "alliance_memberships"') && str_contains($query->sql, 'for update')) {
                $competingMembershipLocks++;
            }
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "alliance_memberships"') || ! str_contains($query->sql, 'for update') || ! in_array((string) $actor->player_id, $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('membership_target_writer');
            try {
                try {
                    $otherWrite();
                    self::fail('Opposing target writes must wait before actor membership acquisition.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                self::assertSame(0, $competingMembershipLocks);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            if (in_array($operation, ['suspend', 'remove membership'], true)) {
                foreach ([$write, $otherWrite] as $attempt) {
                    try {
                        $attempt();
                        self::fail('Equal-rank status targets remain protected.');
                    } catch (AuthorizationException) {
                        self::assertSame($before, $this->state());
                    }
                }
            } else {
                $write();
                if ($operation === 'rank') {
                    self::assertSame(AllianceRank::R3, $other->fresh()?->rank);
                    $afterDemotion = $this->state();
                    try {
                        $otherWrite();
                        self::fail('A specialist manager must respect the current demoted rank ceiling.');
                    } catch (ValidationException $exception) {
                        self::assertArrayHasKey('rank', $exception->errors());
                        self::assertSame($afterDemotion, $this->state());
                    }
                } else {
                    $otherWrite();
                    self::assertSame($operation === 'assign role' ? 2 : 0, DB::table('membership_roles')->where('role_id', $roleId)->count());
                }
                $beforeRetry = $this->state();
                $write();
                self::assertSame($beforeRetry, $this->state());
            }
            self::assertTrue($attempted);
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('membership_target_writer');
        }
    }

    #[DataProvider('operations')]
    public function test_protected_target_scope_does_not_block_independent_alliances(string $operation): void
    {
        [$owner, $allianceId, $target, , $roleId] = $this->fixture($operation);
        [$otherOwner, $otherAllianceId, $otherTarget, , $otherRoleId] = $this->fixture($operation);
        $beforeEvents = DB::table('audit_events')->where('event', $this->auditEvent($operation))->count();
        $primary = $this->competitor();
        $attempted = false;
        DB::listen(function (QueryExecuted $query) use ($primary, $operation, $otherOwner, $otherAllianceId, $otherTarget, $otherRoleId, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "alliances"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('membership_target_writer');
            try {
                $this->write($operation, $otherOwner, $otherAllianceId, (string) $otherTarget->id, $otherRoleId);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $this->write($operation, $owner, $allianceId, (string) $target->id, $roleId);
            self::assertTrue($attempted);
            self::assertSame($beforeEvents + 2, DB::table('audit_events')->where('event', $this->auditEvent($operation))->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('membership_target_writer');
        }
    }

    #[DataProvider('operations')]
    public function test_protected_target_state_and_audit_roll_back_with_failed_delivery(string $operation): void
    {
        [$owner, $allianceId, $target, , $roleId] = $this->fixture($operation);
        $event = match ($operation) {
            'assign role' => 'membership.role_assigned',
            'remove role' => 'membership.role_removed',
            'remove membership' => 'member.left',
            default => 'member.updated',
        };
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use ($event, &$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"') && in_array($event, $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected membership target delivery failure.');
            }
        });
        try {
            $this->write($operation, $owner, $allianceId, (string) $target->id, $roleId);
            self::fail('All protected target effects must commit with delivery.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected membership target delivery failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
        $this->write($operation, $owner, $allianceId, (string) $target->id, $roleId);
        $beforeRetry = $this->state();
        $this->write($operation, $owner, $allianceId, (string) $target->id, $roleId);
        self::assertSame($beforeRetry, $this->state());
    }

    private function write(string $operation, string $actorId, string $allianceId, string $membershipId, string $roleId): void
    {
        match ($operation) {
            'assign role' => app(AssignMembershipRole::class)->handle($allianceId, $actorId, $membershipId, $roleId),
            'remove role' => app(RemoveMembershipRole::class)->handle($allianceId, $actorId, $membershipId, $roleId),
            'rank' => app(UpdateAllianceRank::class)->handle($allianceId, $actorId, $membershipId, AllianceRank::R3),
            'suspend' => app(UpdateMembershipStatus::class)->handle($allianceId, $actorId, $membershipId, MembershipStatus::Suspended),
            'remove membership' => app(UpdateMembershipStatus::class)->handle($allianceId, $actorId, $membershipId, MembershipStatus::Removed),
        };
    }

    private function auditEvent(string $operation): string
    {
        return match ($operation) {
            'assign role' => 'membership.role_assigned',
            'remove role' => 'membership.role_removed',
            'rank' => 'membership.rank_changed',
            default => 'membership.status_changed',
        };
    }

    /** @return array{string,string,AllianceMembership,AllianceMembership,string} */
    private function fixture(string $operation): array
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59287);
        $alliance = $factory->alliance($owner);
        $roleId = app(CreateAllianceRole::class)->handle($alliance->allianceId, $owner->playerId, 'Protected specialist', []);
        $managerRoleId = app(CreateAllianceRole::class)->handle($alliance->allianceId, $owner->playerId, 'Current membership manager', [AlliancePermission::RoleManage]);
        $memberships = [];
        foreach ([1, 2] as $number) {
            $player = $factory->player($factory->account()->userId, 59287);
            $membership = AllianceMembership::query()->create([
                'alliance_id' => $alliance->allianceId, 'player_id' => $player->playerId,
                'rank' => AllianceRank::R4, 'status' => MembershipStatus::Active, 'joined_at' => now(),
            ]);
            app(AssignMembershipRole::class)->handle($alliance->allianceId, $owner->playerId, (string) $membership->id, $managerRoleId);
            if ($operation === 'remove role') {
                app(AssignMembershipRole::class)->handle($alliance->allianceId, $owner->playerId, (string) $membership->id, $roleId);
            }
            $memberships[] = $membership;
        }

        return [$owner->playerId, $alliance->allianceId, $memberships[0], $memberships[1], $roleId];
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.membership_target_writer', array_replace(DB::connection()->getConfig(), ['name' => 'membership_target_writer']));
        DB::connection('membership_target_writer')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        $state = [];
        foreach (['alliance_memberships', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $state['membership_roles'] = DB::table('membership_roles')->orderBy('membership_id')->orderBy('role_id')->get()->toJson();

        return $state;
    }
}
