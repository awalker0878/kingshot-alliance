<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\MultiFactorAuthentication;

use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TotpService;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TwoFactorManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\v3\TestCase;

final class MfaRememberedBrowserInvalidationV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{string}> */
    public static function transitions(): iterable
    {
        foreach (['enable', 'disable', 'regenerate recovery codes'] as $transition) {
            yield $transition => [$transition];
        }
    }

    #[DataProvider('transitions')]
    public function test_confirmed_mfa_transition_invalidates_old_remembered_browser_but_keeps_current_session(string $transition): void
    {
        $enabled = $transition !== 'enable';
        $user = User::factory()->create([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => $enabled ? now() : null,
            'two_factor_recovery_codes' => $enabled ? [hash('sha256', 'prior-recovery-code')] : null,
        ]);
        $guard = Auth::guard('web');
        $cookieName = $guard->getRecallerName();
        $oldToken = $user->getRememberToken();
        $oldCookie = $user->id.'|'.$oldToken.'|'.$guard->hashPasswordForCookie($user->getAuthPassword());

        $this->actingAs($user)->get('/profile')->assertOk();
        $currentSessionId = session()->getId();
        $manager = app(TwoFactorManager::class);
        if ($transition === 'enable') {
            $code = app(TotpService::class)->codeForCounter(
                (string) $user->two_factor_secret,
                intdiv(time(), 30),
            );
            $manager->confirm($user, $code);
        } elseif ($transition === 'disable') {
            $manager->disable($user);
        } else {
            $manager->regenerateRecoveryCodes($user);
        }

        $this->assertAuthenticatedAs($user);
        self::assertNotSame($oldToken, $user->refresh()->getRememberToken());
        self::assertNull(AccountSession::query()
            ->where('session_id_hash', hash('sha256', $currentSessionId))->sole()->revoked_at);

        session()->invalidate();
        Auth::forgetGuards();
        $this->withCookie($cookieName, $oldCookie)->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
        self::assertSame(1, AccountSession::query()->where('user_id', $user->id)->count());
    }
}
