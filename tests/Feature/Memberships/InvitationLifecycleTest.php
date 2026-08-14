<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\MarkRosterEntryLeft;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Actions\AcceptInvitation;
use App\Domain\Memberships\Actions\CreateInvitation;
use App\Domain\Memberships\Actions\ResendInvitation;
use App\Domain\Memberships\Actions\RevokeInvitation;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\InvitationStatus;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Memberships\Models\Invitation;
use App\Domain\Memberships\Queries\FindPendingInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class InvitationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_r5_can_invite_a_specific_roster_player_and_account_can_claim_and_accept_it(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4001, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'invite-owner',
            'current_name' => 'Invite Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Invite Alliance', 'invite-alliance');
        $target = $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Invited Player',
            'game_player_id' => 'invite-target',
        ])->player;

        $issued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $ownerPlayer, $target, 'MEMBER@example.com');

        $record = Invitation::query()->findOrFail($issued->invitationId);
        self::assertSame('member@example.com', $record->email);
        self::assertSame($target->id, $record->player_id);
        self::assertNotSame($issued->token, $record->token_hash);
        self::assertSame(hash('sha256', $issued->token), $record->token_hash);
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'actor_player_id' => $ownerPlayer->id,
            'event' => 'invitation.created',
        ]);

        $membership = $this->app->make(AcceptInvitation::class)
            ->handle($member, $issued->token);

        self::assertSame($alliance->id, $membership->alliance_id);
        self::assertSame($target->id, $membership->player_id);
        self::assertSame(MembershipStatus::Active, $membership->status);
        self::assertSame(AllianceRank::R1, $membership->rank);
        self::assertFalse($membership->roles()->exists());
        self::assertSame($member->id, $target->refresh()->user_id);

        $invitation = Invitation::query()->findOrFail($issued->invitationId)->refresh();
        self::assertSame(InvitationStatus::Accepted, $invitation->status);
        self::assertNull($this->app->make(FindPendingInvitation::class)->byToken($issued->token));
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'actor_player_id' => $target->id,
            'event' => 'invitation.accepted',
        ]);
    }

    public function test_invitation_acceptance_revalidates_that_target_player_is_still_active_on_roster(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'left-before-accept@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4008, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'left-before-accept-owner',
            'current_name' => 'Invite Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Roster Revalidation', 'roster-revalidation');
        $entry = $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Leaving Target',
            'game_player_id' => 'left-before-accept-target',
        ]);
        $issued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $ownerPlayer, $entry->player, $member->email);

        $this->app->make(MarkRosterEntryLeft::class)->handle($alliance, $ownerPlayer, (string) $entry->id);

        $this->expectException(ValidationException::class);
        $this->app->make(AcceptInvitation::class)->handle($member, $issued->token);
    }

    public function test_invitation_cannot_be_accepted_while_alliance_is_not_active(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'suspended-invite@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4009, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'suspended-invite-owner',
            'current_name' => 'Suspended Invite Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Suspended Invite', 'suspended-invite');
        $target = $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Suspended Invite Target',
            'game_player_id' => 'suspended-invite-target',
        ])->player;
        $issued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $ownerPlayer, $target, $member->email);

        $alliance->forceFill(['status' => AllianceStatus::Suspended])->save();

        $this->expectException(ValidationException::class);
        $this->app->make(AcceptInvitation::class)->handle($member, $issued->token);
    }

    public function test_wrong_account_email_cannot_accept_player_invitation(): void
    {
        $owner = User::factory()->create();
        $wrongUser = User::factory()->create(['email' => 'wrong@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4002, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'private-invite-owner',
            'current_name' => 'Private Invite Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Private Invite', 'private-invite');
        $target = $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Right Player',
            'game_player_id' => 'private-invite-target',
        ])->player;
        $issued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $ownerPlayer, $target, 'right@example.com');

        $this->expectException(AuthorizationException::class);
        $this->app->make(AcceptInvitation::class)->handle($wrongUser, $issued->token);
    }

    public function test_ordinary_member_player_cannot_issue_invitations(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4003, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'permissions-owner',
            'current_name' => 'Permissions Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Permissions', 'permissions');
        $memberPlayer = $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Member Player',
            'game_player_id' => 'permissions-member',
        ])->player;
        $memberInvite = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $ownerPlayer, $memberPlayer, $member->email);
        $this->app->make(AcceptInvitation::class)->handle($member, $memberInvite->token);
        $anotherTarget = $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Another Player',
            'game_player_id' => 'permissions-another',
        ])->player;

        $this->expectException(AuthorizationException::class);
        $this->app->make(CreateInvitation::class)
            ->handle($alliance, $memberPlayer, $anotherTarget, 'another@example.com');
    }

    public function test_resend_rotates_token_and_revoke_is_alliance_scoped(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $firstKingdom = Kingdom::query()->create(['number' => 4004, 'status' => 'active']);
        $secondKingdom = Kingdom::query()->create(['number' => 4005, 'status' => 'active']);
        $firstOwnerPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $firstKingdom->id,
            'game_player_id' => 'resend-owner-a',
            'current_name' => 'First Owner',
        ]);
        $secondOwnerPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $secondKingdom->id,
            'game_player_id' => 'resend-owner-b',
            'current_name' => 'Second Owner',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwnerPlayer, 'First', 'invite-first');
        $second = $createAlliance->handle($secondOwnerPlayer, 'Second', 'invite-second');
        $target = $this->app->make(SaveRosterEntry::class)->handle($first, $firstOwnerPlayer, [
            'name' => 'Rotate Target',
            'game_player_id' => 'rotate-target',
        ])->player;
        $issued = $this->app->make(CreateInvitation::class)
            ->handle($first, $firstOwnerPlayer, $target, 'rotate@example.com');

        $resent = $this->app->make(ResendInvitation::class)
            ->handle($first, $firstOwnerPlayer, $issued->invitationId);

        self::assertNotSame($issued->token, $resent->token);
        self::assertNull($this->app->make(FindPendingInvitation::class)->byToken($issued->token));
        self::assertNotNull($this->app->make(FindPendingInvitation::class)->byToken($resent->token));

        try {
            $this->app->make(RevokeInvitation::class)
                ->handle($second, $secondOwnerPlayer, $resent->invitationId);
            self::fail('An invitation from another Alliance must not be addressable.');
        } catch (ModelNotFoundException) {
            self::assertSame(InvitationStatus::Pending, Invitation::query()->findOrFail($resent->invitationId)->refresh()->status);
        }

        $revoked = $this->app->make(RevokeInvitation::class)
            ->handle($first, $firstOwnerPlayer, $resent->invitationId);
        self::assertSame(InvitationStatus::Revoked, $revoked->status);
        self::assertNull($this->app->make(FindPendingInvitation::class)->byToken($resent->token));
    }

    public function test_invitation_only_registration_claims_player_and_activates_player_context(): void
    {
        config()->set('identity.registration_mode', 'invitation_only');

        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4006, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'closed-owner',
            'current_name' => 'Closed Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Closed Alliance', 'closed-alliance');
        $target = $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'New Member Player',
            'game_player_id' => 'closed-new-member',
        ])->player;
        $issued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $ownerPlayer, $target, 'newmember@example.com');

        $response = $this->post('/register', [
            'name' => 'New Member',
            'email' => 'newmember@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'timezone' => 'America/Toronto',
            'invitation_token' => $issued->token,
        ]);

        $newUser = User::query()->where('email', 'newmember@example.com')->sole();
        $response->assertRedirect(route('alliance.overview'));
        $response->assertSessionHas((string) config('identity.active_player_session_key'), $target->id);
        $this->assertAuthenticatedAs($newUser);
        self::assertSame($newUser->id, $target->refresh()->user_id);
        $this->assertDatabaseHas('alliance_memberships', [
            'alliance_id' => $alliance->id,
            'player_id' => $target->id,
            'status' => MembershipStatus::Active->value,
        ]);
        $this->assertDatabaseHas('invitations', [
            'id' => $issued->invitationId,
            'player_id' => $target->id,
            'status' => InvitationStatus::Accepted->value,
        ]);
    }

    public function test_invitation_only_registration_rejects_missing_or_mismatched_invite(): void
    {
        config()->set('identity.registration_mode', 'invitation_only');

        $missing = $this->post('/register', [
            'name' => 'No Invite',
            'email' => 'noinvite@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'timezone' => 'UTC',
        ]);

        $missing->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'noinvite@example.com']);

        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4007, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'email-bound-owner',
            'current_name' => 'Email Bound Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Email Bound', 'email-bound');
        $target = $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Expected Player',
            'game_player_id' => 'email-bound-target',
        ])->player;
        $issued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $ownerPlayer, $target, 'expected@example.com');

        $mismatch = $this->from('/register')->post('/register', [
            'name' => 'Wrong Email',
            'email' => 'wrong@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'timezone' => 'UTC',
            'invitation_token' => $issued->token,
        ]);

        $mismatch->assertRedirect('/register');
        $mismatch->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'wrong@example.com']);
        self::assertNull($target->refresh()->user_id);
    }
}
