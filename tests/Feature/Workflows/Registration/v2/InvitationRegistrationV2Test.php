<?php

declare(strict_types=1);

namespace Tests\Feature\Workflows\Registration\v2;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Membership\Actions\CreateInvitation;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class InvitationRegistrationV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_only_registration_claims_exact_player_and_activates_player_context(): void
    {
        config()->set('accounts.registration_mode', 'invitation_only');
        $scenario = (new ScenarioFactory)->alliance(4560, 'Registration Owner', 'Registration V2', 'registration-v2-4560');
        $entry = app(SaveRosterEntry::class)->handle($scenario['alliance'], $scenario['player'], [
            'name' => 'New Registration Player V2',
            'game_player_id' => 'registration-target-v2-4560',
        ]);
        $issued = app(CreateInvitation::class)->handle(
            $scenario['alliance'],
            $scenario['player'],
            $entry->player,
            'new-registration-v2@example.com',
        );

        $response = $this->post('/register', [
            'name' => 'New Registration V2',
            'email' => 'new-registration-v2@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'timezone' => 'America/Toronto',
            'invitation_token' => $issued->token,
        ]);

        $newUser = User::query()->where('email', 'new-registration-v2@example.com')->sole();
        $response->assertRedirect(route('alliance.overview'));
        $response->assertSessionHas((string) config('game_world.active_player_session_key'), $entry->player_id);
        $this->assertAuthenticatedAs($newUser);
        self::assertSame($newUser->id, $entry->player->refresh()->user_id);
        $this->assertDatabaseHas('alliance_memberships', [
            'alliance_id' => $scenario['alliance']->id,
            'player_id' => $entry->player_id,
            'status' => MembershipStatus::Active->value,
        ]);
        $this->assertDatabaseHas('invitations', [
            'id' => $issued->invitationId,
            'player_id' => $entry->player_id,
            'status' => InvitationStatus::Accepted->value,
        ]);
    }

    public function test_invitation_only_registration_rejects_missing_or_email_mismatched_invitation(): void
    {
        config()->set('accounts.registration_mode', 'invitation_only');

        $this->post('/register', [
            'name' => 'No Invite V2',
            'email' => 'no-invite-v2@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'timezone' => 'UTC',
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'no-invite-v2@example.com']);

        $scenario = (new ScenarioFactory)->alliance(4561, 'Email Owner', 'Email Registration V2', 'email-registration-v2-4561');
        $entry = app(SaveRosterEntry::class)->handle($scenario['alliance'], $scenario['player'], [
            'name' => 'Email Registration Target V2',
            'game_player_id' => 'email-registration-target-v2-4561',
        ]);
        $issued = app(CreateInvitation::class)->handle(
            $scenario['alliance'],
            $scenario['player'],
            $entry->player,
            'expected-registration-v2@example.com',
        );

        $response = $this->from('/register')->post('/register', [
            'name' => 'Wrong Email V2',
            'email' => 'wrong-registration-v2@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'timezone' => 'UTC',
            'invitation_token' => $issued->token,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'wrong-registration-v2@example.com']);
        self::assertNull($entry->player->refresh()->user_id);
    }
}
