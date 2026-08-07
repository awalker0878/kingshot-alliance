<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Identity\AcceptInvitation;
use App\Application\Identity\CreateAlliance;
use App\Application\Identity\CreateInvitation;
use App\Application\Identity\FindPendingInvitation;
use App\Application\Identity\ResendInvitation;
use App\Application\Identity\RevokeInvitation;
use App\Domain\Identity\Authorization\DefaultAllianceRole;
use App\Domain\Identity\Enums\InvitationStatus;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Models\AllianceMembership;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvitationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_issue_and_member_can_accept_a_hashed_one_time_invitation(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Invite Alliance', 'invite-alliance');

        $issued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $owner, 'MEMBER@example.com');

        self::assertSame('member@example.com', $issued->invitation->email);
        self::assertNotSame($issued->token, $issued->invitation->token_hash);
        self::assertSame(hash('sha256', $issued->token), $issued->invitation->token_hash);
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'actor_user_id' => $owner->id,
            'event' => 'invitation.created',
        ]);

        $acceptedAlliance = $this->app->make(AcceptInvitation::class)
            ->handle($member, $issued->token);

        self::assertSame($alliance->id, $acceptedAlliance->id);
        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $member->id)
            ->sole();
        self::assertSame(MembershipStatus::Active, $membership->status);
        self::assertTrue($membership->roles()->where('roles.key', DefaultAllianceRole::Member->value)->exists());

        $invitation = $issued->invitation->refresh();
        self::assertSame(InvitationStatus::Accepted, $invitation->status);
        self::assertSame($member->id, $invitation->accepted_by_user_id);
        self::assertNull($this->app->make(FindPendingInvitation::class)->byToken($issued->token));
    }

    public function test_wrong_email_cannot_accept_invitation(): void
    {
        $owner = User::factory()->create();
        $wrongUser = User::factory()->create(['email' => 'wrong@example.com']);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Private Invite', 'private-invite');
        $issued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $owner, 'right@example.com');

        $this->expectException(AuthorizationException::class);
        $this->app->make(AcceptInvitation::class)->handle($wrongUser, $issued->token);
    }

    public function test_ordinary_member_cannot_issue_invitations(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Permissions', 'permissions');
        $issued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $owner, $member->email);
        $this->app->make(AcceptInvitation::class)->handle($member, $issued->token);

        $this->expectException(AuthorizationException::class);
        $this->app->make(CreateInvitation::class)
            ->handle($alliance, $member, 'another@example.com');
    }

    public function test_resend_rotates_token_and_revoke_is_alliance_scoped(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwner, 'First', 'invite-first');
        $second = $createAlliance->handle($secondOwner, 'Second', 'invite-second');
        $issued = $this->app->make(CreateInvitation::class)
            ->handle($first, $firstOwner, 'rotate@example.com');

        $resent = $this->app->make(ResendInvitation::class)
            ->handle($first, $firstOwner, $issued->invitation->id);

        self::assertNotSame($issued->token, $resent->token);
        self::assertNull($this->app->make(FindPendingInvitation::class)->byToken($issued->token));
        self::assertNotNull($this->app->make(FindPendingInvitation::class)->byToken($resent->token));

        try {
            $this->app->make(RevokeInvitation::class)
                ->handle($second, $secondOwner, $resent->invitation->id);
            self::fail('An invitation from another alliance must not be addressable.');
        } catch (ModelNotFoundException) {
            self::assertSame(InvitationStatus::Pending, $resent->invitation->refresh()->status);
        }

        $revoked = $this->app->make(RevokeInvitation::class)
            ->handle($first, $firstOwner, $resent->invitation->id);
        self::assertSame(InvitationStatus::Revoked, $revoked->status);
        self::assertNull($this->app->make(FindPendingInvitation::class)->byToken($resent->token));
    }

    public function test_invitation_only_registration_is_atomic_and_activates_membership(): void
    {
        config()->set('identity.registration_mode', 'invitation_only');

        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Closed Alliance', 'closed-alliance');
        $issued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $owner, 'newmember@example.com');

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
        $response->assertSessionHas((string) config('identity.active_alliance_session_key'), $alliance->id);
        $this->assertAuthenticatedAs($newUser);
        $this->assertDatabaseHas('alliance_memberships', [
            'alliance_id' => $alliance->id,
            'user_id' => $newUser->id,
            'status' => MembershipStatus::Active->value,
        ]);
        $this->assertDatabaseHas('invitations', [
            'id' => $issued->invitation->id,
            'status' => InvitationStatus::Accepted->value,
            'accepted_by_user_id' => $newUser->id,
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
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Email Bound', 'email-bound');
        $issued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $owner, 'expected@example.com');

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
    }
}
