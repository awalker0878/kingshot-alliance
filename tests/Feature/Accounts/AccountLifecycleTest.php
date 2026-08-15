<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Shared\Audit\Models\AuditEvent;
use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_email_verification_and_unverified_user_cannot_mutate_alliance_state(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Unverified User',
            'email' => 'verify@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'timezone' => 'America/Toronto',
        ]);

        $user = User::query()->where('email', 'verify@example.com')->sole();

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->post('/alliances', [
            'name' => 'Blocked Alliance',
            'slug' => 'blocked-alliance',
            'language' => 'en',
            'timezone' => 'UTC',
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseMissing('alliances', ['slug' => 'blocked-alliance']);
    }

    public function test_signed_verification_link_verifies_user_and_records_audit(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $response = $this->actingAs($user)->get($url);

        $response->assertRedirect(route('dashboard', ['verified' => 1]));
        self::assertTrue($user->refresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'event' => 'auth.email.verified',
        ]);
    }

    public function test_password_reset_changes_password_revokes_tokens_and_is_audited(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => 'OldPassword123',
        ]);
        $user->createToken('test-token');

        $this->post('/forgot-password', ['email' => 'RESET@example.com'])
            ->assertSessionHasNoErrors();

        $token = null;
        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            static function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        self::assertIsString($token);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertRedirect(route('login'));
        self::assertTrue(Hash::check('NewPassword123', $user->refresh()->password));
        self::assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'event' => 'auth.password.reset',
        ]);
    }

    public function test_privileged_membership_change_requires_recent_password_confirmation(): void
    {
        $owner = User::factory()->create(['password' => 'StrongPassword123']);
        $member = User::factory()->create(['email' => 'member@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4501]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'account-lifecycle-owner',
            'current_name' => 'Account Lifecycle Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'account-lifecycle-member',
            'current_name' => 'Account Lifecycle Member',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Confirmed Admin', 'confirmed-admin');
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $ownerPlayer->id])
            ->patch('/alliance/memberships/'.$membership->id.'/status', ['status' => 'suspended'])
            ->assertRedirect(route('password.confirm'));

        self::assertSame('active', $membership->refresh()->status->value);

        $this->post('/confirm-password', ['password' => 'StrongPassword123'])
            ->assertRedirect();

        $this->patch('/alliance/memberships/'.$membership->id.'/status', ['status' => 'suspended'])
            ->assertRedirect(route('alliance.overview'));

        self::assertSame('suspended', $membership->refresh()->status->value);
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $owner->id,
            'event' => 'auth.password.confirmed',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'actor_player_id' => $ownerPlayer->id,
            'actor_user_id' => null,
            'event' => 'membership.status_changed',
        ]);
    }
}
