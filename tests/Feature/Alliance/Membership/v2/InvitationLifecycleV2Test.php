<?php

declare(strict_types=1);

namespace Tests\Feature\Alliance\Membership\v2;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Actions\CreateInvitation;
use App\Contexts\Alliance\Membership\Actions\ResendInvitation;
use App\Contexts\Alliance\Membership\Actions\RevokeInvitation;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\Alliance\Membership\Queries\FindPendingInvitation;
use App\Contexts\Intelligence\Roster\Actions\MarkRosterEntryLeft;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class InvitationLifecycleV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_r5_invites_specific_roster_player_and_acceptance_claims_exact_player(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4520, 'Invite Owner', 'Invite V2', 'invite-v2-4520');
        $member = User::factory()->create(['email' => 'member-v2@example.com']);
        $entry = app(SaveRosterEntry::class)->handle($scenario['alliance'], $scenario['player'], [
            'name' => 'Invited V2 Player',
            'game_player_id' => 'invite-v2-target-4520',
        ]);

        $issued = app(CreateInvitation::class)->handle(
            $scenario['alliance'],
            $scenario['player'],
            $entry->player,
            'MEMBER-V2@EXAMPLE.COM',
        );
        $record = Invitation::query()->findOrFail($issued->invitationId);

        self::assertSame('member-v2@example.com', $record->email);
        self::assertSame($entry->player_id, $record->player_id);
        self::assertNotSame($issued->token, $record->token_hash);
        self::assertSame(hash('sha256', $issued->token), $record->token_hash);
        self::assertSame(InvitationStatus::Pending, $record->status);

        $membership = app(AcceptInvitation::class)->handle($member, $issued->token);

        self::assertSame($scenario['alliance']->id, $membership->alliance_id);
        self::assertSame($entry->player_id, $membership->player_id);
        self::assertSame(MembershipStatus::Active, $membership->status);
        self::assertSame(AllianceRank::R1, $membership->rank);
        self::assertFalse($membership->roles()->exists());
        self::assertSame($member->id, $entry->player->refresh()->user_id);
        self::assertSame(InvitationStatus::Accepted, $record->refresh()->status);
        self::assertNull(app(FindPendingInvitation::class)->byToken($issued->token));
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $scenario['alliance']->id,
            'actor_player_id' => $entry->player_id,
            'event' => 'invitation.accepted',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $scenario['alliance']->id,
            'event_type' => 'invitation.accepted',
            'aggregate_id' => $issued->invitationId,
        ]);
    }

    public function test_acceptance_revalidates_roster_alliance_and_email_boundaries(): void
    {
        $factory = new ScenarioFactory;

        $leftScenario = $factory->alliance(4521, 'Roster Owner', 'Roster Guard V2', 'roster-guard-v2-4521');
        $leftUser = User::factory()->create(['email' => 'left-v2@example.com']);
        $leftEntry = app(SaveRosterEntry::class)->handle($leftScenario['alliance'], $leftScenario['player'], [
            'name' => 'Leaving V2 Target',
            'game_player_id' => 'left-v2-target-4521',
        ]);
        $leftInvite = app(CreateInvitation::class)->handle($leftScenario['alliance'], $leftScenario['player'], $leftEntry->player, $leftUser->email);
        app(MarkRosterEntryLeft::class)->handle($leftScenario['alliance'], $leftScenario['player'], (string) $leftEntry->id);

        try {
            app(AcceptInvitation::class)->handle($leftUser, $leftInvite->token);
            self::fail('Invitation acceptance must revalidate active roster state.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('invitation', $exception->errors());
        }

        $suspendedScenario = $factory->alliance(4522, 'Suspended Owner', 'Suspended V2', 'suspended-v2-4522');
        $suspendedUser = User::factory()->create(['email' => 'suspended-v2@example.com']);
        $suspendedEntry = app(SaveRosterEntry::class)->handle($suspendedScenario['alliance'], $suspendedScenario['player'], [
            'name' => 'Suspended V2 Target',
            'game_player_id' => 'suspended-v2-target-4522',
        ]);
        $suspendedInvite = app(CreateInvitation::class)->handle($suspendedScenario['alliance'], $suspendedScenario['player'], $suspendedEntry->player, $suspendedUser->email);
        $suspendedScenario['alliance']->forceFill(['status' => AllianceStatus::Suspended])->save();

        try {
            app(AcceptInvitation::class)->handle($suspendedUser, $suspendedInvite->token);
            self::fail('Invitation acceptance must fail when the Alliance is not active.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('invitation', $exception->errors());
        }

        $emailScenario = $factory->alliance(4523, 'Email Owner', 'Email Guard V2', 'email-guard-v2-4523');
        $emailEntry = app(SaveRosterEntry::class)->handle($emailScenario['alliance'], $emailScenario['player'], [
            'name' => 'Email V2 Target',
            'game_player_id' => 'email-v2-target-4523',
        ]);
        $emailInvite = app(CreateInvitation::class)->handle($emailScenario['alliance'], $emailScenario['player'], $emailEntry->player, 'right-v2@example.com');

        $this->expectException(AuthorizationException::class);
        app(AcceptInvitation::class)->handle(
            User::factory()->create(['email' => 'wrong-v2@example.com']),
            $emailInvite->token,
        );
    }

    public function test_ordinary_member_cannot_issue_invitation_and_active_player_cannot_be_invited_elsewhere(): void
    {
        $factory = new ScenarioFactory;
        $first = $factory->alliance(4524, 'First Owner', 'First V2', 'first-v2-4524');
        $member = User::factory()->create(['email' => 'active-member-v2@example.com']);
        $memberEntry = app(SaveRosterEntry::class)->handle($first['alliance'], $first['player'], [
            'name' => 'Active Member V2',
            'game_player_id' => 'active-member-v2-4524',
        ]);
        $memberInvite = app(CreateInvitation::class)->handle($first['alliance'], $first['player'], $memberEntry->player, $member->email);
        app(AcceptInvitation::class)->handle($member, $memberInvite->token);
        $anotherEntry = app(SaveRosterEntry::class)->handle($first['alliance'], $first['player'], [
            'name' => 'Another Target V2',
            'game_player_id' => 'another-target-v2-4524',
        ]);

        try {
            app(CreateInvitation::class)->handle($first['alliance'], $memberEntry->player, $anotherEntry->player, 'another-v2@example.com');
            self::fail('R1 members must not issue invitations.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        $secondOwner = $factory->playerFor(User::factory()->create(), 4524, 'Second Owner', 'second-owner-v2-4524')['player'];
        $secondAlliance = app(CreateAlliance::class)->handle($secondOwner, 'Second V2', 'second-v2-4524');
        app(SaveRosterEntry::class)->handle($secondAlliance, $secondOwner, [
            'name' => $memberEntry->player->current_name,
            'game_player_id' => $memberEntry->player->game_player_id,
        ]);

        try {
            app(CreateInvitation::class)->handle($secondAlliance, $secondOwner, $memberEntry->player, $member->email);
            self::fail('A Player active in another Alliance must not receive a second invitation.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('player_id', $exception->errors());
        }
    }

    public function test_replacement_resend_and_revoke_rotate_tokens_without_cross_alliance_access(): void
    {
        $factory = new ScenarioFactory;
        $first = $factory->alliance(4525, 'Token Owner', 'Token V2', 'token-v2-4525');
        $entry = app(SaveRosterEntry::class)->handle($first['alliance'], $first['player'], [
            'name' => 'Token Target V2',
            'game_player_id' => 'token-target-v2-4525',
        ]);
        $create = app(CreateInvitation::class);

        $original = $create->handle($first['alliance'], $first['player'], $entry->player, 'token-v2@example.com');
        $replacement = $create->handle($first['alliance'], $first['player'], $entry->player, 'token-v2@example.com');

        self::assertSame(InvitationStatus::Revoked, Invitation::query()->findOrFail($original->invitationId)->status);
        self::assertSame(InvitationStatus::Pending, Invitation::query()->findOrFail($replacement->invitationId)->status);
        self::assertNull(app(FindPendingInvitation::class)->byToken($original->token));
        self::assertNotNull(app(FindPendingInvitation::class)->byToken($replacement->token));
        $this->assertDatabaseHas('audit_events', [
            'event' => 'invitation.revoked',
            'subject_id' => $original->invitationId,
        ]);

        $resent = app(ResendInvitation::class)->handle($first['alliance'], $first['player'], $replacement->invitationId);
        self::assertNotSame($replacement->token, $resent->token);
        self::assertNull(app(FindPendingInvitation::class)->byToken($replacement->token));
        self::assertNotNull(app(FindPendingInvitation::class)->byToken($resent->token));

        $other = $factory->alliance(4526, 'Other Owner', 'Other V2', 'other-v2-4526');
        try {
            app(RevokeInvitation::class)->handle($other['alliance'], $other['player'], $resent->invitationId);
            self::fail('Another Alliance must not address the invitation.');
        } catch (ModelNotFoundException) {
            self::assertSame(InvitationStatus::Pending, Invitation::query()->findOrFail($resent->invitationId)->status);
        }

        $revoked = app(RevokeInvitation::class)->handle($first['alliance'], $first['player'], $resent->invitationId);
        self::assertSame(InvitationStatus::Revoked, $revoked->status);
        self::assertNull(app(FindPendingInvitation::class)->byToken($resent->token));
    }
}
