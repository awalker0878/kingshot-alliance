<?php

declare(strict_types=1);

namespace Tests\Feature\Contexts\Accounts\MultiFactorAuthentication;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class MfaEnrollmentV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_setup_reaches_the_profile_once_without_storing_plaintext_in_the_user_row(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['accounts.recent_authentication_at' => now()->timestamp])
            ->post('/profile/two-factor')->assertRedirect(route('profile.show'));

        $this->withCookie((string) config('session.cookie'), session()->getId());

        $setup = session('twoFactorSetup');
        self::assertIsArray($setup);
        self::assertIsString($setup['secret']);
        self::assertIsString($setup['provisioning_uri']);
        $user->refresh();
        self::assertSame($setup['secret'], $user->two_factor_secret);
        self::assertNotSame($setup['secret'], $user->getRawOriginal('two_factor_secret'));

        $this->get('/profile')->assertOk()->assertInertia(static fn (Assert $page): Assert => $page
            ->component('Accounts/Governor/Profile')
            ->where('user.twoFactorPending', true)
            ->where('twoFactorSetup', $setup)
            ->where('twoFactorRecoveryCodes', null));
        self::assertFalse(session()->has('twoFactorSetup'));

        $this->get('/profile')->assertOk()->assertInertia(static fn (Assert $page): Assert => $page
            ->where('twoFactorSetup', null)
            ->where('twoFactorRecoveryCodes', null));
    }

    public function test_confirmed_and_regenerated_recovery_codes_are_displayed_once_and_persist_only_as_hashes(): void
    {
        $user = User::factory()->google()->create();
        $this->actingAs($user)
            ->withSession(['accounts.recent_authentication_at' => now()->timestamp])
            ->post('/profile/two-factor')->assertRedirect(route('profile.show'));
        $this->withCookie((string) config('session.cookie'), session()->getId());
        $user->refresh();
        $code = app(TotpService::class)->codeForCounter((string) $user->two_factor_secret, intdiv(time(), 30));
        $this->get('/profile')->assertOk();

        $this->post('/profile/two-factor/confirm', ['code' => $code])->assertRedirect(route('profile.show'));
        $initialCodes = $this->assertRecoveryDelivery($user);

        $this->post('/profile/two-factor/recovery-codes')->assertRedirect(route('profile.show'));
        $replacementCodes = $this->assertRecoveryDelivery($user);

        self::assertSame([], array_intersect($initialCodes, $replacementCodes));
        self::assertSame([], array_intersect(
            array_map(static fn (string $value): string => hash('sha256', $value), $initialCodes),
            $user->two_factor_recovery_codes ?? [],
        ));
    }

    /** @return list<string> */
    private function assertRecoveryDelivery(User $user): array
    {
        $codes = session('twoFactorRecoveryCodes');
        self::assertIsArray($codes);
        self::assertCount(8, $codes);
        $user->refresh();
        self::assertNotNull($user->two_factor_confirmed_at);
        self::assertSame(
            array_map(static fn (string $value): string => hash('sha256', $value), $codes),
            $user->two_factor_recovery_codes,
        );

        $this->get('/profile')->assertOk()->assertInertia(static fn (Assert $page): Assert => $page
            ->component('Accounts/Governor/Profile')
            ->where('user.twoFactorEnabled', true)
            ->where('user.recoveryCodeCount', 8)
            ->where('twoFactorSetup', null)
            ->where('twoFactorRecoveryCodes', $codes));
        self::assertFalse(session()->has('twoFactorRecoveryCodes'));
        $this->get('/profile')->assertOk()->assertInertia(static fn (Assert $page): Assert => $page
            ->where('twoFactorSetup', null)
            ->where('twoFactorRecoveryCodes', null));

        return $codes;
    }
}
