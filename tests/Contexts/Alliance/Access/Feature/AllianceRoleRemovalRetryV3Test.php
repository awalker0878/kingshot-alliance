<?php

declare(strict_types=1);

namespace Tests\Contexts\Alliance\Access\Feature;

use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Access\Actions\RemoveMembershipRole;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class AllianceRoleRemovalRetryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_absent_and_repeated_removal_do_not_create_false_events(): void
    {
        $s = $this->scenario();
        $before = $this->state();
        app(RemoveMembershipRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, $s['membershipId'], $s['roleId']);
        self::assertSame($before, $this->state());

        app(AssignMembershipRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, $s['membershipId'], $s['roleId']);
        app(RemoveMembershipRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, $s['membershipId'], $s['roleId']);
        $afterRemoval = $this->state();
        app(RemoveMembershipRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, $s['membershipId'], $s['roleId']);
        self::assertSame($afterRemoval, $this->state());
        self::assertSame(1, DB::table('audit_events')->where('event', 'membership.role_removed')->count());
        self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'membership.role_removed')->count());
    }

    public function test_distinct_removals_have_distinct_delivery_keys_even_at_the_same_clock_instant(): void
    {
        $s = $this->scenario();
        $this->freezeTime();
        for ($index = 0; $index < 2; $index++) {
            app(AssignMembershipRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, $s['membershipId'], $s['roleId']);
            app(RemoveMembershipRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, $s['membershipId'], $s['roleId']);
        }
        $keys = DB::table('outbox_messages')->where('event_type', 'membership.role_removed')->pluck('idempotency_key')->all();
        self::assertCount(2, array_unique($keys));
        self::assertSame(2, DB::table('audit_events')->where('event', 'membership.role_removed')->count());
        self::assertSame(0, DB::table('membership_roles')->where('role_id', $s['roleId'])->count());
    }

    public function test_absent_assignment_still_requires_current_removal_authority(): void
    {
        $s = $this->scenario();
        $before = $this->state();
        try {
            app(RemoveMembershipRole::class)->handle($s['alliance']->allianceId, $s['member']->playerId, $s['membershipId'], $s['roleId']);
            self::fail('Idempotency must not bypass current authorization.');
        } catch (AuthorizationException) {
            self::assertSame($before, $this->state());
        }
    }

    public function test_late_failure_restores_the_assignment_and_retry_records_one_removal(): void
    {
        $s = $this->scenario();
        app(AssignMembershipRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, $s['membershipId'], $s['roleId']);
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"') && in_array('membership.role_removed', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected removal outbox failure.');
            }
        });
        try {
            app(RemoveMembershipRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, $s['membershipId'], $s['roleId']);
            self::fail('Removal must fail atomically.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected removal outbox failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
        app(RemoveMembershipRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, $s['membershipId'], $s['roleId']);
        self::assertSame(1, DB::table('audit_events')->where('event', 'membership.role_removed')->count());
        self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'membership.role_removed')->count());
    }

    /** @return array{alliance:AllianceReference,leader:PlayerReference,member:PlayerReference,membershipId:string,roleId:string} */
    private function scenario(): array
    {
        $factory = app(ScenarioFactory::class);
        $user = $factory->authUser();
        $leader = $factory->player((int) $user->id, 59258);
        $alliance = $factory->alliance($leader);
        $member = $factory->unclaimedPlayer(59258);
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId, 'player_id' => $member->playerId,
            'rank' => AllianceRank::R1, 'status' => MembershipStatus::Active, 'joined_at' => now(),
        ]);
        $membershipId = (string) $membership->id;
        $roleId = app(CreateAllianceRole::class)->handle($alliance->allianceId, $leader->playerId, 'Retry specialist', []);

        return compact('alliance', 'leader', 'member', 'membershipId', 'roleId');
    }

    /** @return array<string,mixed> */
    private function state(): array
    {
        return [
            'assignments' => DB::table('membership_roles')->orderBy('membership_id')->orderBy('role_id')->get()->toJson(),
            'audit' => DB::table('audit_events')->count(),
            'outbox' => DB::table('outbox_messages')->count(),
        ];
    }
}
