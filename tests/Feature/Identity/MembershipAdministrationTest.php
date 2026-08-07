<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Actions\AssignMembershipRole;
use App\Domain\Authorization\Actions\RemoveMembershipRole;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Actions\AcceptInvitation;
use App\Domain\Memberships\Actions\CreateInvitation;
use App\Domain\Memberships\Actions\LeaveAlliance;
use App\Domain\Memberships\Actions\UpdateMembershipStatus;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Models\OutboxMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class MembershipAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_leader_can_manage_lower_rank_member_but_not_owner(): void
    {
        $owner = User::factory()->create();
        $leader = User::factory()->create(['email' => 'leader@example.com']);
        $member = User::factory()->create(['email' => 'member@example.com']);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Hierarchy', 'hierarchy');

        $leaderMembership = $this->join($alliance, $owner, $leader);
        $memberMembership = $this->join($alliance, $owner, $member);
        $leaderRole = $this->role($alliance, DefaultAllianceRole::Leader);

        $this->app->make(AssignMembershipRole::class)
            ->handle($alliance, $owner, $leaderMembership->id, $leaderRole->id);

        $updated = $this->app->make(UpdateMembershipStatus::class)
            ->handle($alliance, $leader, $memberMembership->id, MembershipStatus::Suspended);
        self::assertSame(MembershipStatus::Suspended, $updated->status);

        $ownerMembership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $owner->id)
            ->sole();

        $this->expectException(AuthorizationException::class);
        $this->app->make(UpdateMembershipStatus::class)
            ->handle($alliance, $leader, $ownerMembership->id, MembershipStatus::Suspended);
    }

    public function test_non_owner_cannot_change_role_assignments(): void
    {
        $owner = User::factory()->create();
        $leader = User::factory()->create(['email' => 'leader@example.com']);
        $member = User::factory()->create(['email' => 'member@example.com']);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Role Security', 'role-security');
        $leaderMembership = $this->join($alliance, $owner, $leader);
        $memberMembership = $this->join($alliance, $owner, $member);
        $leaderRole = $this->role($alliance, DefaultAllianceRole::Leader);
        $officerRole = $this->role($alliance, DefaultAllianceRole::Officer);

        $this->app->make(AssignMembershipRole::class)
            ->handle($alliance, $owner, $leaderMembership->id, $leaderRole->id);

        $this->expectException(AuthorizationException::class);
        $this->app->make(AssignMembershipRole::class)
            ->handle($alliance, $leader, $memberMembership->id, $officerRole->id);
    }

    public function test_last_owner_cannot_leave_or_remove_owner_role(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Owner Safety', 'owner-safety');
        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $owner->id)
            ->sole();
        $ownerRole = $this->role($alliance, DefaultAllianceRole::Owner);

        try {
            $this->app->make(LeaveAlliance::class)->handle($alliance, $owner);
            self::fail('The last owner must not be able to leave.');
        } catch (ValidationException) {
            self::assertSame(MembershipStatus::Active, $membership->refresh()->status);
        }

        $this->expectException(ValidationException::class);
        $this->app->make(RemoveMembershipRole::class)
            ->handle($alliance, $owner, $membership->id, $ownerRole->id);
    }

    public function test_owner_can_transfer_ownership_then_leave_without_privilege_residue(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create(['email' => 'second-owner@example.com']);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($firstOwner, 'Transfer', 'transfer');
        $secondMembership = $this->join($alliance, $firstOwner, $secondOwner);
        $ownerRole = $this->role($alliance, DefaultAllianceRole::Owner);

        $this->app->make(AssignMembershipRole::class)
            ->handle($alliance, $firstOwner, $secondMembership->id, $ownerRole->id);

        $left = $this->app->make(LeaveAlliance::class)->handle($alliance, $firstOwner);

        self::assertSame(MembershipStatus::Left, $left->status);
        self::assertFalse($left->roles()->exists());
        self::assertTrue($secondMembership->roles()->where('roles.key', DefaultAllianceRole::Owner->value)->exists());
    }

    public function test_removed_member_loses_roles_and_reactivation_gets_only_member_role(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'reactivate@example.com']);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Reactivation', 'reactivation');
        $membership = $this->join($alliance, $owner, $member);
        $officerRole = $this->role($alliance, DefaultAllianceRole::Officer);

        $this->app->make(AssignMembershipRole::class)
            ->handle($alliance, $owner, $membership->id, $officerRole->id);

        $removed = $this->app->make(UpdateMembershipStatus::class)
            ->handle($alliance, $owner, $membership->id, MembershipStatus::Removed);
        self::assertFalse($removed->roles()->exists());

        try {
            $this->app->make(AssignMembershipRole::class)
                ->handle($alliance, $owner, $membership->id, $officerRole->id);
            self::fail('Inactive memberships must not receive hidden role assignments.');
        } catch (ValidationException) {
            self::assertFalse($membership->refresh()->roles()->exists());
        }

        $active = $this->app->make(UpdateMembershipStatus::class)
            ->handle($alliance, $owner, $membership->id, MembershipStatus::Active);
        self::assertSame(MembershipStatus::Active, $active->status);
        self::assertSame(
            [DefaultAllianceRole::Member->value],
            $active->roles()->pluck('roles.key')->sort()->values()->all(),
        );
    }

    public function test_role_assignment_is_idempotent_and_can_be_repeated_after_removal(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'repeat-role@example.com']);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Repeat Roles', 'repeat-roles');
        $membership = $this->join($alliance, $owner, $member);
        $officerRole = $this->role($alliance, DefaultAllianceRole::Officer);
        $assign = $this->app->make(AssignMembershipRole::class);

        $assign->handle($alliance, $owner, $membership->id, $officerRole->id);
        $assign->handle($alliance, $owner, $membership->id, $officerRole->id);

        self::assertSame(1, OutboxMessage::query()
            ->where('event_type', 'membership.role_assigned')
            ->where('aggregate_id', $membership->id)
            ->count());

        $this->app->make(RemoveMembershipRole::class)
            ->handle($alliance, $owner, $membership->id, $officerRole->id);
        $reassigned = $assign->handle($alliance, $owner, $membership->id, $officerRole->id);

        self::assertTrue($reassigned->roles()->where('roles.id', $officerRole->id)->exists());
        self::assertSame(2, OutboxMessage::query()
            ->where('event_type', 'membership.role_assigned')
            ->where('aggregate_id', $membership->id)
            ->count());
    }

    public function test_member_can_leave_rejoin_and_leave_again_without_outbox_collision(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'returning@example.com']);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Return Cycle', 'return-cycle');
        $membership = $this->join($alliance, $owner, $member);

        $this->app->make(LeaveAlliance::class)->handle($alliance, $member);

        $issued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $owner, $member->email);
        $this->app->make(AcceptInvitation::class)->handle($member, $issued->token);

        $leftAgain = $this->app->make(LeaveAlliance::class)->handle($alliance, $member);

        self::assertSame($membership->id, $leftAgain->id);
        self::assertSame(MembershipStatus::Left, $leftAgain->status);
        self::assertSame(2, OutboxMessage::query()
            ->where('event_type', 'membership.left')
            ->where('aggregate_id', $membership->id)
            ->count());
    }

    public function test_membership_admin_action_cannot_address_another_alliance(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwner, 'First Tenant', 'first-tenant');
        $second = $createAlliance->handle($secondOwner, 'Second Tenant', 'second-tenant');
        $secondMembership = AllianceMembership::query()
            ->where('alliance_id', $second->id)
            ->where('user_id', $secondOwner->id)
            ->sole();

        $this->expectException(ModelNotFoundException::class);
        $this->app->make(UpdateMembershipStatus::class)
            ->handle($first, $firstOwner, $secondMembership->id, MembershipStatus::Suspended);
    }

    private function join(Alliance $alliance, User $owner, User $user): AllianceMembership
    {
        $issued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $owner, $user->email);
        $this->app->make(AcceptInvitation::class)->handle($user, $issued->token);

        return AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $user->id)
            ->sole();
    }

    private function role(Alliance $alliance, DefaultAllianceRole $role): Role
    {
        return Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', $role->value)
            ->sole();
    }
}
