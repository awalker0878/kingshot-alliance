<?php

declare(strict_types=1);

namespace Tests\Integration\Contexts\Accounts\MultiFactorAuthentication;

use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Credentials\Actions\RemovePassword;
use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Actions\RemoveAccountIdentity;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\MultiFactorAuthentication\Actions\CompleteMfaLogin;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TotpService;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TwoFactorManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MfaLoginChallengeV3Test extends TestCase
{
    use DatabaseMigrations;

    private const RECOVERY_CODE = 'a1b2-c3d4-e5f6-0123';

    protected function setUp(): void
    {
        parent::setUp();

        // CI uses shared rate-limit storage; each case represents a distinct client.
        $client = hash('sha256', self::class.'::'.$this->nameWithDataSet());
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::'.substr($client, 0, 4).':'.substr($client, 4, 4)]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    #[DataProvider('loginMethods')]
    public function test_current_primary_proof_completes_with_totp_or_recovery_and_registers_the_final_session(bool $google, bool $recovery): void
    {
        $user = $this->mfaUser($google);
        $this->startLogin($user, $google);
        $this->assertGuest();
        $state = session('accounts.mfa_login');
        self::assertIsArray($state);
        self::assertSame($google ? 'google' : 'password', $state['method']);
        self::assertStringNotContainsString((string) $user->two_factor_secret, json_encode($state, JSON_THROW_ON_ERROR));
        self::assertSame(0, AccountSession::query()->where('user_id', $user->id)->count());

        $this->post('/two-factor-challenge', $recovery
            ? ['recovery_code' => self::RECOVERY_CODE]
            : ['code' => $this->code($user)])
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('accounts.mfa_login')
            ->assertSessionHas('accounts.recent_authentication_method', $google ? 'google' : 'password');

        $this->assertAuthenticatedAs($user);
        $session = AccountSession::query()->where('user_id', $user->id)->sole();
        self::assertSame(hash('sha256', session()->getId()), $session->session_id_hash);
        self::assertNull($session->revoked_at);
        self::assertCount($recovery ? 0 : 1, $user->refresh()->two_factor_recovery_codes ?? []);
        self::assertSame(1, DB::table('audit_events')->where('event', 'auth.login')->where('actor_user_id', $user->id)->count());
    }

    /** @return array<string, array{bool, bool}> */
    public static function loginMethods(): array
    {
        return [
            'password and TOTP' => [false, false],
            'password and recovery' => [false, true],
            'Google and TOTP' => [true, false],
            'Google and recovery' => [true, true],
        ];
    }

    #[DataProvider('staleCredentials')]
    public function test_pending_proof_cannot_survive_credential_or_lifecycle_changes(string $change): void
    {
        $google = str_starts_with($change, 'google');
        $user = $this->mfaUser($google);
        if ($google) {
            $user->forceFill(['password' => Hash::make('password')])->save();
        } elseif ($change === 'password removed') {
            $this->attachGoogle($user, 'alternate-google-subject');
        }
        $this->startLogin($user, $google);

        switch ($change) {
            case 'password changed':
                $user->forceFill(['password' => Hash::make('ChangedPassword123')])->save();
                break;
            case 'password removed':
                app(RemovePassword::class)->handle((int) $user->id, null);
                break;
            case 'google removed':
            case 'google replaced':
                app(RemoveAccountIdentity::class)->handle((int) $user->id, 'google', null);
                if ($change === 'google replaced') {
                    $this->attachGoogle($user, 'replacement-google-subject');
                }
                break;
            case 'MFA disabled':
                app(TwoFactorManager::class)->disable($user);
                break;
            case 'MFA replaced':
                app(TwoFactorManager::class)->disable($user);
                app(TwoFactorManager::class)->begin($user);
                app(TwoFactorManager::class)->confirm($user, $this->code($user->refresh()));
                break;
            case 'account anonymized':
                app(AnonymizeAccount::class)->handle((int) $user->id, 'pending-mfa-test');
                break;
        }

        $user->refresh();
        $code = $user->two_factor_secret === null ? '123456' : $this->code($user);
        $this->post('/two-factor-challenge', ['code' => $code])
            ->assertSessionHasErrors('code')
            ->assertSessionMissing('accounts.mfa_login')
            ->assertSessionMissing('accounts.recent_authentication_at');
        $this->assertGuest();
        self::assertSame(0, AccountSession::query()->where('user_id', $user->id)->count());
        self::assertSame(0, DB::table('audit_events')->where('event', 'auth.login')->where('actor_user_id', $user->id)->count());
    }

    /** @return array<string, array{string}> */
    public static function staleCredentials(): array
    {
        return array_combine(
            $changes = ['password changed', 'password removed', 'google removed', 'google replaced', 'MFA disabled', 'MFA replaced', 'account anonymized'],
            array_map(static fn (string $change): array => [$change], $changes),
        );
    }

    public function test_challenge_expires_at_ten_minutes_without_consuming_a_recovery_code(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = $this->mfaUser(false);
        $this->startLogin($user, false);
        $this->travel(600)->seconds();

        $this->post('/two-factor-challenge', ['recovery_code' => self::RECOVERY_CODE])
            ->assertSessionHasErrors('code')->assertSessionMissing('accounts.mfa_login');
        $this->get('/two-factor-challenge')->assertRedirect(route('login'));
        $this->assertGuest();
        self::assertSame([hash('sha256', self::RECOVERY_CODE)], $user->refresh()->two_factor_recovery_codes);
    }

    public function test_invalid_second_factor_can_retry_but_success_consumes_the_primary_proof(): void
    {
        $user = $this->mfaUser(false);
        $this->startLogin($user, false);
        $this->post('/two-factor-challenge', ['code' => 'not-a-code'])
            ->assertSessionHasErrors('code')->assertSessionHas('accounts.mfa_login');
        $this->assertGuest();

        $code = $this->code($user);
        $this->post('/two-factor-challenge', ['code' => $code])
            ->assertRedirect(route('dashboard'))->assertSessionMissing('accounts.mfa_login');
        $request = Request::create('/two-factor-challenge', 'POST');
        $request->setLaravelSession(session()->driver());

        $this->expectException(ValidationException::class);
        app(CompleteMfaLogin::class)->handle($request, $code, '');
    }

    public function test_second_factor_attempt_limit_remains_enforced(): void
    {
        $user = $this->mfaUser(false);
        $this->startLogin($user, false);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/two-factor-challenge', ['code' => 'not-a-code'])->assertSessionHasErrors('code');
        }

        $this->post('/two-factor-challenge', ['code' => $this->code($user)])->assertStatus(429);
        $this->assertGuest();
        self::assertTrue(session()->has('accounts.mfa_login'));
    }

    private function mfaUser(bool $google): User
    {
        return ($google ? User::factory()->google() : User::factory())->create([
            'two_factor_secret' => app(TotpService::class)->generateSecret(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [hash('sha256', self::RECOVERY_CODE)],
        ]);
    }

    private function startLogin(User $user, bool $google): void
    {
        if ($google) {
            config()->set('services.google', ['client_id' => 'client', 'client_secret' => 'secret', 'redirect' => 'http://localhost/auth/google/callback']);
            $identity = $user->accountIdentities()->where('provider', 'google')->sole();
            $googleUser = (new SocialiteUser)->setRaw(['email_verified' => true])
                ->map(['id' => $identity->provider_subject, 'email' => $identity->provider_email, 'name' => 'MFA User']);
            $provider = Mockery::mock(Provider::class);
            $provider->shouldReceive('user')->once()->andReturn($googleUser);
            Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
            $this->withSession(['accounts.google_operation' => [
                'intent' => 'login', 'user_id' => null, 'invitation_token' => null, 'started_at' => now()->timestamp,
            ]])->get('/auth/google/callback')->assertRedirect(route('two-factor.login'));
        } else {
            $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true])
                ->assertRedirect(route('two-factor.login'));
        }

        $this->withCookie((string) config('session.cookie'), session()->getId());
    }

    private function code(User $user): string
    {
        return app(TotpService::class)->codeForCounter((string) $user->two_factor_secret, intdiv(time(), 30));
    }

    private function attachGoogle(User $user, string $subject): void
    {
        AccountIdentity::query()->create([
            'user_id' => $user->id, 'provider' => 'google', 'provider_subject' => $subject,
            'provider_email' => $user->email, 'provider_email_verified_at' => now(), 'linked_at' => now(),
        ]);
    }
}
