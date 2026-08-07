<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Alliances\Models\Alliance;

use App\Domain\Memberships\Actions\AcceptInvitation;
use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Memberships\Actions\CreateInvitation;
use App\Domain\Alliances\Models\AllianceMembership;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Identity\Models\User;
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
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Confirmed Admin', 'confirmed-admin');
        $issued = $this->app->make(CreateInvitation::class)
            ->handle($alliance, $owner, $member->email);
        $this->app->make(AcceptInvitation::class)->handle($member, $issued->token);
        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $member->id)
            ->sole();
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
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
        self::assertTrue(AuditEvent::query()->where('event', 'membership.status_changed')->exists());
    }
}
