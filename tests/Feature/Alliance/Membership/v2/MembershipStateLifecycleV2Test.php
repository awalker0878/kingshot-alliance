<?php

declare(strict_types=1);

namespace Tests\Feature\Alliance\Membership\v2;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Actions\CreateInvitation;
use App\Contexts\Alliance\Membership\Actions\LeaveAlliance;
use App\Contexts\Alliance\Membership\Actions\UpdateAllianceRank;
use App\Contexts\Alliance\Membership\Actions\UpdateMembershipStatus;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class MembershipStateLifecycleV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_removed_member_loses_specialist_roles_and_reactivation_returns_as_r1(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4540, 'State Owner', 'State V2', 'state-v2-4540');
        $memberUser = User::factory()->create(['email' => 'state-member-v2@example.com']);
        $entry = app(SaveRosterEntry::class)->handle($scenario['alliance'], $scenario['player'], [
            'name' => 'State Member V2',
            'game_player_id' => 'state-member-v2-4540',
        ]);
        $invite = app(CreateInvitation::class)->handle($scenario['alliance'], $scenario['player'], $entry->player, $memberUser->email);
        $membership = app(AcceptInvitation::class)->handle($memberUser, $invite->token);
        $role = Role::query()
            ->where('alliance_id', $scenario['alliance']->id)
            ->where('key', DefaultAllianceRole::EventCoordinator->value)
            ->sole();

        app(UpdateAllianceRank::class)->handle($scenario['alliance'], $scenario['player'], $membership->id, AllianceRank::R4);
        app(AssignMembershipRole::class)->handle($scenario['alliance'], $scenario['player'], $membership->id, $role->id);
        self::assertTrue($membership->refresh()->roles()->where('roles.id', $role->id)->exists());

        $removed = app(UpdateMembershipStatus::class)->handle(
            $scenario['alliance'],
            $scenario['player'],
            $membership->id,
            MembershipStatus::Removed,
        );
        self::assertFalse($removed->roles()->exists());

        $active = app(UpdateMembershipStatus::class)->handle(
            $scenario['alliance'],
            $scenario['player'],
            $membership->id,
            MembershipStatus::Active,
        );
        self::assertSame(MembershipStatus::Active, $active->status);
        self::assertSame(AllianceRank::R1, $active->rank);
        self::assertFalse($active->roles()->exists());
    }

    public function test_suspension_preserves_rank_but_reactivation_still_consumes_member_capacity(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4541, 'Capacity Owner', 'Capacity V2', 'capacity-v2-4541');
        $memberUser = User::factory()->create(['email' => 'capacity-member-v2@example.com']);
        $entry = app(SaveRosterEntry::class)->handle($scenario['alliance'], $scenario['player'], [
            'name' => 'Capacity Member V2',
            'game_player_id' => 'capacity-member-v2-4541',
        ]);
        $invite = app(CreateInvitation::class)->handle($scenario['alliance'], $scenario['player'], $entry->player, $memberUser->email);
        $membership = app(AcceptInvitation::class)->handle($memberUser, $invite->token);
        app(UpdateAllianceRank::class)->handle($scenario['alliance'], $scenario['player'], $membership->id, AllianceRank::R4);

        $suspended = app(UpdateMembershipStatus::class)->handle(
            $scenario['alliance'],
            $scenario['player'],
            $membership->id,
            MembershipStatus::Suspended,
        );
        self::assertSame(AllianceRank::R4, $suspended->rank);

        $active = app(UpdateMembershipStatus::class)->handle(
            $scenario['alliance'],
            $scenario['player'],
            $membership->id,
            MembershipStatus::Active,
        );
        self::assertSame(AllianceRank::R4, $active->rank);

        app(UpdateMembershipStatus::class)->handle(
            $scenario['alliance'],
            $scenario['player'],
            $membership->id,
            MembershipStatus::Suspended,
        );
        DB::table('platform_plan_entitlements')
            ->where('plan_code', 'standard')
            ->where('entitlement_key', 'members.max')
            ->update(['limit_value' => 1]);

        try {
            app(UpdateMembershipStatus::class)->handle(
                $scenario['alliance'],
                $scenario['player'],
                $membership->id,
                MembershipStatus::Active,
            );
            self::fail('Reactivation must consume the same member capacity as a new seat.');
        } catch (ValidationException) {
            self::assertSame(MembershipStatus::Suspended, $membership->refresh()->status);
        }
    }

    public function test_player_can_leave_rejoin_and_leave_again_without_event_collision(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4542, 'Return Owner', 'Return V2', 'return-v2-4542');
        $memberUser = User::factory()->create(['email' => 'return-member-v2@example.com']);
        $entry = app(SaveRosterEntry::class)->handle($scenario['alliance'], $scenario['player'], [
            'name' => 'Return Member V2',
            'game_player_id' => 'return-member-v2-4542',
        ]);
        $invite = app(CreateInvitation::class)->handle($scenario['alliance'], $scenario['player'], $entry->player, $memberUser->email);
        $membership = app(AcceptInvitation::class)->handle($memberUser, $invite->token);

        app(LeaveAlliance::class)->handle($scenario['alliance'], $entry->player);
        $reissued = app(CreateInvitation::class)->handle($scenario['alliance'], $scenario['player'], $entry->player, $memberUser->email);
        $rejoined = app(AcceptInvitation::class)->handle($memberUser, $reissued->token);
        $leftAgain = app(LeaveAlliance::class)->handle($scenario['alliance'], $entry->player);

        self::assertSame($membership->id, $rejoined->id);
        self::assertSame($membership->id, $leftAgain->id);
        self::assertSame(MembershipStatus::Left, $leftAgain->status);
        self::assertSame(2, OutboxMessage::query()
            ->where('event_type', 'membership.left')
            ->where('aggregate_id', $membership->id)
            ->count());
    }

    public function test_membership_admin_action_cannot_address_another_alliance_membership(): void
    {
        $factory = new ScenarioFactory;
        $first = $factory->alliance(4543, 'First Tenant', 'First Tenant V2', 'first-tenant-v2-4543');
        $second = $factory->alliance(4544, 'Second Tenant', 'Second Tenant V2', 'second-tenant-v2-4544');
        $foreignMembership = AllianceMembership::query()
            ->where('alliance_id', $second['alliance']->id)
            ->where('player_id', $second['player']->id)
            ->sole();

        $this->expectException(ModelNotFoundException::class);
        app(UpdateMembershipStatus::class)->handle(
            $first['alliance'],
            $first['player'],
            $foreignMembership->id,
            MembershipStatus::Suspended,
        );
    }
}
