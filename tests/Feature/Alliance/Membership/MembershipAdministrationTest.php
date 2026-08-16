<?php

declare(strict_types=1);

namespace Tests\Feature\Alliance\Membership;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\RemoveMembershipRole;
use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Actions\CreateInvitation;
use App\Contexts\Alliance\Membership\Actions\LeaveAlliance;
use App\Contexts\Alliance\Membership\Actions\TransferAllianceLeadership;
use App\Contexts\Alliance\Membership\Actions\UpdateAllianceRank;
use App\Contexts\Alliance\Membership\Actions\UpdateMembershipStatus;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use App\Shared\Messaging\Models\OutboxMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class MembershipAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_r4_player_can_manage_lower_rank_player_but_not_r5(): void
    {
        $r5User = User::factory()->create();
        $r4User = User::factory()->create(['email' => 'r4@example.com']);
        $memberUser = User::factory()->create(['email' => 'member@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4101, 'status' => 'active']);
        $r5Player = Player::query()->create([
            'user_id' => $r5User->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'hierarchy-r5',
            'current_name' => 'Hierarchy R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($r5Player, 'Hierarchy', 'hierarchy');
        $r4Player = $this->app->make(SaveRosterEntry::class)->handle($alliance, $r5Player, [
            'name' => 'Hierarchy R4',
            'game_player_id' => 'hierarchy-r4',
        ])->player;
        $r4Invite = $this->app->make(CreateInvitation::class)->handle($alliance, $r5Player, $r4Player, $r4User->email);
        $r4Membership = $this->app->make(AcceptInvitation::class)->handle($r4User, $r4Invite->token);
        $memberPlayer = $this->app->make(SaveRosterEntry::class)->handle($alliance, $r5Player, [
            'name' => 'Hierarchy Member',
            'game_player_id' => 'hierarchy-member',
        ])->player;
        $memberInvite = $this->app->make(CreateInvitation::class)->handle($alliance, $r5Player, $memberPlayer, $memberUser->email);
        $memberMembership = $this->app->make(AcceptInvitation::class)->handle($memberUser, $memberInvite->token);
        $this->app->make(UpdateAllianceRank::class)->handle($alliance, $r5Player, $r4Membership->id, AllianceRank::R4);

        $updated = $this->app->make(UpdateMembershipStatus::class)
            ->handle($alliance, $r4Player, $memberMembership->id, MembershipStatus::Suspended);
        self::assertSame(MembershipStatus::Suspended, $updated->status);

        $r5Membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $r5Player->id)
            ->sole();

        $this->expectException(AuthorizationException::class);
        $this->app->make(UpdateMembershipStatus::class)
            ->handle($alliance, $r4Player, $r5Membership->id, MembershipStatus::Suspended);
    }

    public function test_r4_player_cannot_change_specialist_role_assignments(): void
    {
        $r5User = User::factory()->create();
        $r4User = User::factory()->create(['email' => 'r4-role@example.com']);
        $memberUser = User::factory()->create(['email' => 'member-role@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4102, 'status' => 'active']);
        $r5Player = Player::query()->create([
            'user_id' => $r5User->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'role-security-r5',
            'current_name' => 'Role Security R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($r5Player, 'Role Security', 'role-security');
        $r4Player = $this->app->make(SaveRosterEntry::class)->handle($alliance, $r5Player, [
            'name' => 'Role Security R4',
            'game_player_id' => 'role-security-r4',
        ])->player;
        $r4Invite = $this->app->make(CreateInvitation::class)->handle($alliance, $r5Player, $r4Player, $r4User->email);
        $r4Membership = $this->app->make(AcceptInvitation::class)->handle($r4User, $r4Invite->token);
        $memberPlayer = $this->app->make(SaveRosterEntry::class)->handle($alliance, $r5Player, [
            'name' => 'Role Security Member',
            'game_player_id' => 'role-security-member',
        ])->player;
        $memberInvite = $this->app->make(CreateInvitation::class)->handle($alliance, $r5Player, $memberPlayer, $memberUser->email);
        $memberMembership = $this->app->make(AcceptInvitation::class)->handle($memberUser, $memberInvite->token);
        $this->app->make(UpdateAllianceRank::class)->handle($alliance, $r5Player, $r4Membership->id, AllianceRank::R4);
        $eventCoordinator = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::EventCoordinator->value)
            ->sole();

        $this->expectException(AuthorizationException::class);
        $this->app->make(AssignMembershipRole::class)
            ->handle($alliance, $r4Player, $memberMembership->id, $eventCoordinator->id);
    }

    public function test_r5_player_cannot_leave_before_leadership_transfer(): void
    {
        $r5User = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4103, 'status' => 'active']);
        $r5Player = Player::query()->create([
            'user_id' => $r5User->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'leave-r5',
            'current_name' => 'Leave R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($r5Player, 'R5 Safety', 'r5-safety');

        $this->expectException(ValidationException::class);
        $this->app->make(LeaveAlliance::class)->handle($alliance, $r5Player);
    }

    public function test_r5_transfer_is_player_to_player_then_previous_r5_can_leave(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create(['email' => 'second-r5@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4104, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $firstUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'transfer-r5-first',
            'current_name' => 'First R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($firstPlayer, 'Transfer', 'transfer');
        $secondPlayer = $this->app->make(SaveRosterEntry::class)->handle($alliance, $firstPlayer, [
            'name' => 'Second R5',
            'game_player_id' => 'transfer-r5-second',
        ])->player;
        $invite = $this->app->make(CreateInvitation::class)->handle($alliance, $firstPlayer, $secondPlayer, $secondUser->email);
        $secondMembership = $this->app->make(AcceptInvitation::class)->handle($secondUser, $invite->token);

        $this->app->make(TransferAllianceLeadership::class)
            ->handle($alliance, $firstPlayer, $secondPlayer->id);

        $firstMembership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $firstPlayer->id)
            ->sole();

        self::assertSame(AllianceRank::R4, $firstMembership->rank);
        self::assertSame(AllianceRank::R5, $secondMembership->refresh()->rank);
        self::assertSame(1, AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->where('rank', AllianceRank::R5->value)
            ->count());

        $left = $this->app->make(LeaveAlliance::class)->handle($alliance, $firstPlayer);
        self::assertSame(MembershipStatus::Left, $left->status);
    }

    public function test_removed_player_loses_specialist_roles_and_reactivation_returns_as_r1(): void
    {
        $r5User = User::factory()->create();
        $memberUser = User::factory()->create(['email' => 'reactivate@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4105, 'status' => 'active']);
        $r5Player = Player::query()->create([
            'user_id' => $r5User->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'reactivate-r5',
            'current_name' => 'Reactivate R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($r5Player, 'Reactivation', 'reactivation');
        $memberPlayer = $this->app->make(SaveRosterEntry::class)->handle($alliance, $r5Player, [
            'name' => 'Reactivate Member',
            'game_player_id' => 'reactivate-member',
        ])->player;
        $invite = $this->app->make(CreateInvitation::class)->handle($alliance, $r5Player, $memberPlayer, $memberUser->email);
        $membership = $this->app->make(AcceptInvitation::class)->handle($memberUser, $invite->token);
        $eventCoordinator = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::EventCoordinator->value)
            ->sole();

        $this->app->make(UpdateAllianceRank::class)->handle($alliance, $r5Player, $membership->id, AllianceRank::R4);
        $this->app->make(AssignMembershipRole::class)
            ->handle($alliance, $r5Player, $membership->id, $eventCoordinator->id);

        $removed = $this->app->make(UpdateMembershipStatus::class)
            ->handle($alliance, $r5Player, $membership->id, MembershipStatus::Removed);
        self::assertFalse($removed->roles()->exists());

        try {
            $this->app->make(AssignMembershipRole::class)
                ->handle($alliance, $r5Player, $membership->id, $eventCoordinator->id);
            self::fail('Inactive memberships must not receive hidden role assignments.');
        } catch (ValidationException) {
            self::assertFalse($membership->refresh()->roles()->exists());
        }

        $active = $this->app->make(UpdateMembershipStatus::class)
            ->handle($alliance, $r5Player, $membership->id, MembershipStatus::Active);
        self::assertSame(MembershipStatus::Active, $active->status);
        self::assertSame(AllianceRank::R1, $active->rank);
        self::assertFalse($active->roles()->exists());
    }

    public function test_suspended_r4_player_keeps_rank_when_reactivated(): void
    {
        $r5User = User::factory()->create();
        $r4User = User::factory()->create(['email' => 'suspended-r4@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4106, 'status' => 'active']);
        $r5Player = Player::query()->create([
            'user_id' => $r5User->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'suspend-rank-r5',
            'current_name' => 'Suspend Rank R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($r5Player, 'Suspend Rank', 'suspend-rank');
        $r4Player = $this->app->make(SaveRosterEntry::class)->handle($alliance, $r5Player, [
            'name' => 'Suspended R4',
            'game_player_id' => 'suspend-rank-r4',
        ])->player;
        $invite = $this->app->make(CreateInvitation::class)->handle($alliance, $r5Player, $r4Player, $r4User->email);
        $membership = $this->app->make(AcceptInvitation::class)->handle($r4User, $invite->token);
        $this->app->make(UpdateAllianceRank::class)->handle($alliance, $r5Player, $membership->id, AllianceRank::R4);

        $this->app->make(UpdateMembershipStatus::class)
            ->handle($alliance, $r5Player, $membership->id, MembershipStatus::Suspended);
        $active = $this->app->make(UpdateMembershipStatus::class)
            ->handle($alliance, $r5Player, $membership->id, MembershipStatus::Active);

        self::assertSame(AllianceRank::R4, $active->rank);
    }

    public function test_membership_reactivation_obeys_the_same_member_capacity_limit_as_new_invitations(): void
    {
        $r5User = User::factory()->create();
        $memberUser = User::factory()->create(['email' => 'capacity-reactivate@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4111, 'status' => 'active']);
        $r5Player = Player::query()->create([
            'user_id' => $r5User->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'capacity-reactivate-r5',
            'current_name' => 'Capacity Reactivate R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($r5Player, 'Capacity Reactivation', 'capacity-reactivation');
        $memberPlayer = $this->app->make(SaveRosterEntry::class)->handle($alliance, $r5Player, [
            'name' => 'Capacity Reactivate Member',
            'game_player_id' => 'capacity-reactivate-member',
        ])->player;
        $invite = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $r5Player, $memberPlayer, $memberUser->email);
        $membership = $this->app->make(AcceptInvitation::class)->handle($memberUser, $invite->token);
        $this->app->make(UpdateMembershipStatus::class)
            ->handle($alliance, $r5Player, $membership->id, MembershipStatus::Suspended);

        DB::table('platform_plan_entitlements')
            ->where('plan_code', 'standard')
            ->where('entitlement_key', 'members.max')
            ->update(['limit_value' => 1]);

        try {
            $this->app->make(UpdateMembershipStatus::class)
                ->handle($alliance, $r5Player, $membership->id, MembershipStatus::Active);
            self::fail('Membership reactivation must consume the same plan member capacity as a new seat.');
        } catch (ValidationException) {
            self::assertSame(MembershipStatus::Suspended, $membership->refresh()->status);
        }
    }

    public function test_specialist_role_assignment_is_idempotent_and_can_be_repeated_after_removal(): void
    {
        $r5User = User::factory()->create();
        $memberUser = User::factory()->create(['email' => 'repeat-role@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4107, 'status' => 'active']);
        $r5Player = Player::query()->create([
            'user_id' => $r5User->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'repeat-role-r5',
            'current_name' => 'Repeat Role R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($r5Player, 'Repeat Roles', 'repeat-roles');
        $memberPlayer = $this->app->make(SaveRosterEntry::class)->handle($alliance, $r5Player, [
            'name' => 'Repeat Role Member',
            'game_player_id' => 'repeat-role-member',
        ])->player;
        $invite = $this->app->make(CreateInvitation::class)->handle($alliance, $r5Player, $memberPlayer, $memberUser->email);
        $membership = $this->app->make(AcceptInvitation::class)->handle($memberUser, $invite->token);
        $eventCoordinator = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::EventCoordinator->value)
            ->sole();
        $assign = $this->app->make(AssignMembershipRole::class);

        $assign->handle($alliance, $r5Player, $membership->id, $eventCoordinator->id);
        $assign->handle($alliance, $r5Player, $membership->id, $eventCoordinator->id);

        self::assertSame(1, OutboxMessage::query()
            ->where('event_type', 'membership.role_assigned')
            ->where('aggregate_id', $membership->id)
            ->count());

        $this->app->make(RemoveMembershipRole::class)
            ->handle($alliance, $r5Player, $membership->id, $eventCoordinator->id);
        $reassigned = $assign->handle($alliance, $r5Player, $membership->id, $eventCoordinator->id);

        self::assertTrue($reassigned->roles()->where('roles.id', $eventCoordinator->id)->exists());
        self::assertSame(2, OutboxMessage::query()
            ->where('event_type', 'membership.role_assigned')
            ->where('aggregate_id', $membership->id)
            ->count());
    }

    public function test_player_can_leave_rejoin_and_leave_again_without_outbox_collision(): void
    {
        $r5User = User::factory()->create();
        $memberUser = User::factory()->create(['email' => 'returning@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4108, 'status' => 'active']);
        $r5Player = Player::query()->create([
            'user_id' => $r5User->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'return-cycle-r5',
            'current_name' => 'Return Cycle R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($r5Player, 'Return Cycle', 'return-cycle');
        $memberPlayer = $this->app->make(SaveRosterEntry::class)->handle($alliance, $r5Player, [
            'name' => 'Returning Player',
            'game_player_id' => 'return-cycle-member',
        ])->player;
        $invite = $this->app->make(CreateInvitation::class)->handle($alliance, $r5Player, $memberPlayer, $memberUser->email);
        $membership = $this->app->make(AcceptInvitation::class)->handle($memberUser, $invite->token);

        $this->app->make(LeaveAlliance::class)->handle($alliance, $memberPlayer);

        $reissued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $r5Player, $memberPlayer, $memberUser->email);
        $this->app->make(AcceptInvitation::class)->handle($memberUser, $reissued->token);

        $leftAgain = $this->app->make(LeaveAlliance::class)->handle($alliance, $memberPlayer);

        self::assertSame($membership->id, $leftAgain->id);
        self::assertSame(MembershipStatus::Left, $leftAgain->status);
        self::assertSame(2, OutboxMessage::query()
            ->where('event_type', 'membership.left')
            ->where('aggregate_id', $membership->id)
            ->count());
    }

    public function test_membership_admin_action_cannot_address_another_alliance_membership(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $firstKingdom = Kingdom::query()->create(['number' => 4109, 'status' => 'active']);
        $secondKingdom = Kingdom::query()->create(['number' => 4110, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $firstUser->id,
            'current_kingdom_id' => $firstKingdom->id,
            'game_player_id' => 'tenant-first-r5',
            'current_name' => 'First Tenant R5',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $secondUser->id,
            'current_kingdom_id' => $secondKingdom->id,
            'game_player_id' => 'tenant-second-r5',
            'current_name' => 'Second Tenant R5',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstPlayer, 'First Tenant', 'first-tenant');
        $second = $createAlliance->handle($secondPlayer, 'Second Tenant', 'second-tenant');
        $secondMembership = AllianceMembership::query()
            ->where('alliance_id', $second->id)
            ->where('player_id', $secondPlayer->id)
            ->sole();

        $this->expectException(ModelNotFoundException::class);
        $this->app->make(UpdateMembershipStatus::class)
            ->handle($first, $firstPlayer, $secondMembership->id, MembershipStatus::Suspended);
    }
}
