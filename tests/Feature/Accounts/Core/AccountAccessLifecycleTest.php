<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts\Core;

use App\Contexts\Accounts\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class AccountAccessLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_registration_canonicalizes_identity_emits_durable_event_and_requires_email_verification(): void
    {
        config()->set('accounts.registration_mode', 'open');
        Notification::fake();

        $this->post('/register', [
            'name' => 'V2 Account',
            'email' => 'V2.Account@Example.COM',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'timezone' => 'America/Toronto',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->sole();
        self::assertSame('v2.account@example.com', $user->email);
        self::assertSame('America/Toronto', $user->timezone);
        self::assertTrue(Hash::check('StrongPassword123', $user->password));
        self::assertFalse($user->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertDatabaseHas('audit_events', ['actor_user_id' => $user->id, 'event' => 'user.registered']);
        $this->assertDatabaseHas('outbox_messages', ['aggregate_id' => (string) $user->id, 'event_type' => 'user.registered']);
    }

    public function test_registration_policy_is_enforced_before_account_creation(): void
    {
        config()->set('accounts.registration_mode', 'invitation_only');

        $this->post('/register', [
            'name' => 'Blocked Account',
            'email' => 'blocked-v2@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'timezone' => 'UTC',
        ])->assertForbidden();

        self::assertSame(0, User::query()->count());
    }

    public function test_login_is_case_insensitive_and_logout_audits_the_same_account_principal(): void
    {
        $user = User::factory()->create([
            'email' => 'member-v2@example.com',
            'password' => 'StrongPassword123',
        ]);

        $this->post('/login', [
            'email' => 'MEMBER-V2@EXAMPLE.COM',
            'password' => 'StrongPassword123',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_events', ['actor_user_id' => $user->id, 'event' => 'auth.login']);

        $this->delete('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_events', ['actor_user_id' => $user->id, 'event' => 'auth.logout']);
    }

    public function test_signed_verification_and_password_reset_complete_account_security_lifecycle(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create([
            'email' => 'lifecycle-v2@example.com',
            'password' => 'OldPassword123',
        ]);
        $user->createToken('v2-token');

        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(15), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);
        $this->actingAs($user)->get($verificationUrl)
            ->assertRedirect(route('dashboard', ['verified' => 1]));
        self::assertTrue($user->refresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('audit_events', ['actor_user_id' => $user->id, 'event' => 'auth.email.verified']);

        $this->delete('/logout');
        $this->post('/forgot-password', ['email' => 'LIFECYCLE-V2@EXAMPLE.COM'])->assertSessionHasNoErrors();
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, static function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        self::assertIsString($token);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'lifecycle-v2@example.com',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect(route('login'));

        self::assertTrue(Hash::check('NewPassword123', $user->refresh()->password));
        self::assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('audit_events', ['actor_user_id' => $user->id, 'event' => 'auth.password.reset']);
    }
}
