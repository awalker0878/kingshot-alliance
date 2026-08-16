<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts\Core;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Accounts\Services\TotpService;
use App\Contexts\Accounts\Services\TwoFactorManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class MultiFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_requires_totp_confirmation_and_stores_only_hashed_recovery_codes(): void
    {
        $user = User::factory()->create();
        $manager = app(TwoFactorManager::class);
        $totp = app(TotpService::class);

        $setup = $manager->begin($user);
        self::assertStringStartsWith('otpauth://totp/', $setup['provisioning_uri']);
        self::assertNull($user->refresh()->two_factor_confirmed_at);

        $recoveryCodes = $manager->confirm(
            $user,
            $totp->codeForCounter($setup['secret'], intdiv(time(), 30)),
        );
        $user->refresh();

        self::assertNotNull($user->two_factor_confirmed_at);
        self::assertCount(8, $recoveryCodes);
        self::assertCount(8, $user->two_factor_recovery_codes ?? []);
        self::assertNotContains($recoveryCodes[0], $user->two_factor_recovery_codes ?? []);
        $this->assertDatabaseHas('audit_events', ['actor_user_id' => $user->id, 'event' => 'auth.mfa.enabled']);
    }

    public function test_confirmed_mfa_cannot_be_restarted_or_downgraded_by_beginning_enrollment_again(): void
    {
        $user = User::factory()->create();
        $manager = app(TwoFactorManager::class);
        $totp = app(TotpService::class);
        $setup = $manager->begin($user);
        $manager->confirm($user, $totp->codeForCounter($setup['secret'], intdiv(time(), 30)));
        $secret = $user->refresh()->two_factor_secret;

        try {
            $manager->begin($user);
            self::fail('Confirmed MFA must not be overwritten.');
        } catch (ValidationException) {
            self::assertSame($secret, $user->refresh()->two_factor_secret);
        }
    }

    public function test_password_login_stops_at_mfa_boundary_until_totp_succeeds(): void
    {
        $user = User::factory()->create([
            'email' => 'mfa-v2@example.com',
            'password' => 'StrongPassword123',
        ]);
        $manager = app(TwoFactorManager::class);
        $totp = app(TotpService::class);
        $setup = $manager->begin($user);
        $manager->confirm($user, $totp->codeForCounter($setup['secret'], intdiv(time(), 30)));

        $this->post('/login', [
            'email' => 'mfa-v2@example.com',
            'password' => 'StrongPassword123',
        ])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->assertDatabaseMissing('audit_events', ['actor_user_id' => $user->id, 'event' => 'auth.login']);

        $this->post('/two-factor-challenge', [
            'code' => $totp->codeForCounter($setup['secret'], intdiv(time(), 30)),
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_events', ['actor_user_id' => $user->id, 'event' => 'auth.login']);
    }

    public function test_recovery_code_is_single_use_and_disabling_mfa_clears_all_secrets(): void
    {
        $user = User::factory()->create([
            'email' => 'recover-v2@example.com',
            'password' => 'StrongPassword123',
        ]);
        $manager = app(TwoFactorManager::class);
        $totp = app(TotpService::class);
        $setup = $manager->begin($user);
        $codes = $manager->confirm($user, $totp->codeForCounter($setup['secret'], intdiv(time(), 30)));

        $this->post('/login', ['email' => $user->email, 'password' => 'StrongPassword123'])
            ->assertRedirect(route('two-factor.login'));
        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]])
            ->assertRedirect(route('dashboard'));
        $this->delete('/logout');

        $this->post('/login', ['email' => $user->email, 'password' => 'StrongPassword123'])
            ->assertRedirect(route('two-factor.login'));
        $this->from('/two-factor-challenge')
            ->post('/two-factor-challenge', ['recovery_code' => $codes[0]])
            ->assertSessionHasErrors('code');

        $manager->disable($user);
        $user->refresh();
        self::assertNull($user->two_factor_secret);
        self::assertNull($user->two_factor_recovery_codes);
        self::assertNull($user->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_events', ['actor_user_id' => $user->id, 'event' => 'auth.mfa.disabled']);
    }
}
