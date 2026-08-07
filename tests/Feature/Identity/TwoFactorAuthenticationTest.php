<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Identity\TotpService;
use App\Application\Identity\TwoFactorManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_enroll_confirm_and_receive_one_time_recovery_codes(): void
    {
        $user = User::factory()->create();
        $manager = $this->app->make(TwoFactorManager::class);
        $totp = $this->app->make(TotpService::class);

        $setup = $manager->begin($user);
        $user->refresh();

        self::assertSame($setup['secret'], $user->two_factor_secret);
        self::assertNull($user->two_factor_confirmed_at);
        self::assertStringStartsWith('otpauth://totp/', $setup['provisioning_uri']);

        $code = $totp->codeForCounter($setup['secret'], intdiv(time(), 30));
        $recoveryCodes = $manager->confirm($user, $code);
        $user->refresh();

        self::assertNotNull($user->two_factor_confirmed_at);
        self::assertCount(8, $recoveryCodes);
        self::assertCount(8, $user->two_factor_recovery_codes ?? []);
        self::assertFalse(in_array($recoveryCodes[0], $user->two_factor_recovery_codes ?? [], true));
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'event' => 'auth.mfa.enabled',
        ]);
    }

    public function test_enabled_mfa_cannot_be_overwritten_by_starting_enrollment_again(): void
    {
        $user = User::factory()->create();
        $manager = $this->app->make(TwoFactorManager::class);
        $totp = $this->app->make(TotpService::class);
        $setup = $manager->begin($user);
        $manager->confirm($user, $totp->codeForCounter($setup['secret'], intdiv(time(), 30)));
        $user->refresh();
        $confirmedSecret = $user->two_factor_secret;
        $confirmedAt = $user->two_factor_confirmed_at;

        try {
            $manager->begin($user);
            self::fail('Confirmed MFA must not be downgraded by restarting enrollment.');
        } catch (ValidationException) {
            $user->refresh();
            self::assertSame($confirmedSecret, $user->two_factor_secret);
            self::assertEquals($confirmedAt, $user->two_factor_confirmed_at);
        }
    }

    public function test_confirmed_mfa_interrupts_password_login_until_totp_challenge_succeeds(): void
    {
        $user = User::factory()->create([
            'email' => 'mfa@example.com',
            'password' => 'StrongPassword123',
        ]);
        $manager = $this->app->make(TwoFactorManager::class);
        $totp = $this->app->make(TotpService::class);
        $setup = $manager->begin($user);
        $manager->confirm($user, $totp->codeForCounter($setup['secret'], intdiv(time(), 30)));

        $login = $this->post('/login', [
            'email' => 'mfa@example.com',
            'password' => 'StrongPassword123',
            'remember' => true,
        ]);

        $login->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $login->assertSessionHas('identity.two_factor_challenge_user_id', $user->id);
        $this->assertDatabaseMissing('audit_events', [
            'actor_user_id' => $user->id,
            'event' => 'auth.login',
        ]);

        $challenge = $this->post('/two-factor-challenge', [
            'code' => $totp->codeForCounter($setup['secret'], intdiv(time(), 30)),
        ]);

        $challenge->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'event' => 'auth.login',
        ]);
    }

    public function test_recovery_code_is_single_use_during_login_challenge(): void
    {
        $user = User::factory()->create([
            'email' => 'recover-mfa@example.com',
            'password' => 'StrongPassword123',
        ]);
        $manager = $this->app->make(TwoFactorManager::class);
        $totp = $this->app->make(TotpService::class);
        $setup = $manager->begin($user);
        $recoveryCodes = $manager->confirm(
            $user,
            $totp->codeForCounter($setup['secret'], intdiv(time(), 30)),
        );
        $recoveryCode = $recoveryCodes[0];

        $this->post('/login', [
            'email' => 'recover-mfa@example.com',
            'password' => 'StrongPassword123',
        ])->assertRedirect(route('two-factor.login'));

        $this->post('/two-factor-challenge', [
            'recovery_code' => $recoveryCode,
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->delete('/logout');
        $this->post('/login', [
            'email' => 'recover-mfa@example.com',
            'password' => 'StrongPassword123',
        ])->assertRedirect(route('two-factor.login'));

        $this->from('/two-factor-challenge')->post('/two-factor-challenge', [
            'recovery_code' => $recoveryCode,
        ])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_disabling_mfa_clears_secret_recovery_codes_and_confirmation(): void
    {
        $user = User::factory()->create();
        $manager = $this->app->make(TwoFactorManager::class);
        $totp = $this->app->make(TotpService::class);
        $setup = $manager->begin($user);
        $manager->confirm($user, $totp->codeForCounter($setup['secret'], intdiv(time(), 30)));

        $manager->disable($user);
        $user->refresh();

        self::assertNull($user->two_factor_secret);
        self::assertNull($user->two_factor_recovery_codes);
        self::assertNull($user->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'event' => 'auth.mfa.disabled',
        ]);
    }
}
