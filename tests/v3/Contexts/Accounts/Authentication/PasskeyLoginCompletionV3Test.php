<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Authentication\Models\AccountPasskey;
use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TotpService;
use Closure;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Support\WebAuthn;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\v3\Support\WebAuthnAssertionFixture;
use Tests\v3\Support\WebAuthnRegistrationFixture;
use Tests\v3\TestCase;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

final class PasskeyLoginCompletionV3Test extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('passkeys.enabled', true);
        config()->set('passkeys.relying_party_id', 'accounts.example.test');
        config()->set('passkeys.allowed_origins', ['https://accounts.example.test']);
        $client = hash('sha256', self::class.'::'.$this->nameWithDataSet());
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::'.substr($client, 0, 4).':'.substr($client, 4, 4)]);
    }

    /** @return iterable<string,array{bool,bool}> */
    public static function browsers(): iterable
    {
        yield 'HTML passkey-only account' => [false, false];
        yield 'JSON passkey-only account' => [true, false];
        yield 'JSON user-verifying passkey satisfies MFA' => [true, true];
    }

    #[DataProvider('browsers')]
    public function test_real_signed_http_login_completes_the_current_credential_before_redirecting(bool $json, bool $mfa): void
    {
        [$user, $passkey, $fixture] = $this->account($mfa, false);
        $options = $this->verificationOptions();
        $initialSession = session()->getId();
        $loginEvents = 0;
        Event::listen(Login::class, static function (Login $event) use ($user, &$loginEvents): void {
            self::assertSame(0, DB::transactionLevel());
            self::assertSame((int) $user->id, (int) $event->user->id);
            self::assertSame(1, AccountSession::query()->where('user_id', $user->id)->whereNull('revoked_at')->count());
            self::assertSame(1, DB::table('audit_events')->where('event', 'auth.login')->count());
            $loginEvents++;
        });
        $payload = ['credential' => $fixture->browserAssertion($options, $user->getPasskeyUserHandle()), 'remember' => true];
        $response = $json ? $this->postJson(route('passkey.login'), $payload) : $this->post(route('passkey.login'), $payload);
        if ($json) {
            $response->assertOk()->assertJsonPath('redirect', route('dashboard'));
        } else {
            $response->assertRedirect(route('dashboard'));
        }

        $this->assertAuthenticatedAs($user);
        self::assertSame(1, $loginEvents);
        self::assertNotSame($initialSession, session()->getId());
        self::assertSame(1, $passkey->refresh()->credential['counter']);
        self::assertSame('passkey', session('accounts.recent_authentication_method'));
        self::assertSame($passkey->public_id, session('accounts.recent_authentication_credential'));
        self::assertFalse(session()->has('accounts.mfa_login'));
        self::assertFalse(session()->has('passkey.verification_options'));
        self::assertSame($mfa ? [hash('sha256', 'unused-recovery-code')] : null, $user->refresh()->two_factor_recovery_codes);
        self::assertNotEmpty($user->getRememberToken());
        $metadata = json_decode((string) DB::table('audit_events')->where('event', 'auth.login')->sole()->metadata, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('user_verifying_passkey', $metadata['mfa_method']);
    }

    /** @return iterable<string,array{string}> */
    public static function failures(): iterable
    {
        foreach (['audit', 'session row', 'raw rotation'] as $failure) {
            yield $failure => [$failure];
        }
    }

    #[DataProvider('failures')]
    public function test_failed_login_keeps_verified_counter_but_grants_no_session_or_recent_proof(string $failure): void
    {
        [$user, $passkey, $fixture] = $this->account();
        $options = $this->verificationOptions();
        $failed = false;
        $this->observeRotation(static function () use ($failure, &$failed): void {
            self::assertSame(0, DB::transactionLevel());
            if ($failure === 'raw rotation') {
                $failed = true;
                throw new RuntimeException('Injected passkey login failure.');
            }
        });
        DB::listen(static function (QueryExecuted $query) use ($failure, &$failed): void {
            $target = $failure === 'session row'
                ? str_starts_with($query->sql, 'insert into "account_sessions"')
                : $failure === 'audit' && str_starts_with($query->sql, 'insert into "audit_events"') && in_array('auth.login', $query->bindings, true);
            if (! $failed && $target) {
                $failed = true;
                throw new RuntimeException('Injected passkey login failure.');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->postJson(route('passkey.login'), ['credential' => $fixture->browserAssertion($options, $user->getPasskeyUserHandle()), 'remember' => true]);
            self::fail('Exercise failure after maintained assertion verification.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected passkey login failure.', $exception->getMessage());
        }

        self::assertTrue($failed);
        $this->assertGuest();
        self::assertSame(1, $passkey->refresh()->credential['counter']);
        self::assertSame(1, DB::table('audit_events')->where('event', 'auth.passkey.verified')->count());
        self::assertSame(0, DB::table('audit_events')->where('event', 'auth.login')->count());
        self::assertSame(0, AccountSession::query()->count());
        self::assertNull($user->refresh()->getRawOriginal('remember_token'));
        self::assertFalse(session()->has('accounts.recent_authentication_at'));
    }

    /** @return iterable<string,array{bool}> */
    public static function invalidCeremonies(): iterable
    {
        yield 'wrong challenge' => [false];
        yield 'no pending ceremony' => [true];
    }

    #[DataProvider('invalidCeremonies')]
    public function test_invalid_http_ceremony_is_validation_feedback_and_a_fresh_ceremony_can_retry(bool $missing): void
    {
        [$user, $passkey, $fixture] = $this->account();
        $options = $missing ? app(GenerateVerificationOptions::class)() : $this->verificationOptions();
        $this->postJson(route('passkey.login'), ['credential' => $fixture->browserAssertion($options,
            $user->getPasskeyUserHandle(), challenge: $missing ? null : 'wrong-challenge')])
            ->assertUnprocessable()->assertJsonValidationErrors('credential');
        $this->assertGuest();
        self::assertSame(0, $passkey->refresh()->credential['counter']);
        self::assertSame(0, DB::table('audit_events')->where('event', 'auth.passkey.verified')->count());
        self::assertSame(0, AccountSession::query()->count());
        self::assertFalse(session()->has('accounts.recent_authentication_at'));

        $options = $this->verificationOptions();
        $this->postJson(route('passkey.login'), ['credential' => $fixture->browserAssertion($options, $user->getPasskeyUserHandle())])
            ->assertOk()->assertJsonPath('redirect', route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_credential_deleted_after_assertion_during_rotation_cannot_complete_http_login(): void
    {
        [$user, $passkey, $fixture] = $this->account();
        $options = $this->verificationOptions();
        $removed = false;
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.passkey_login_competitor', array_replace(DB::connection()->getConfig(), ['name' => 'passkey_login_competitor']));
        DB::connection('passkey_login_competitor')->statement("SET lock_timeout = '100ms'");
        $this->observeRotation(static function () use ($user, $passkey, $primary, &$removed): void {
            self::assertSame(0, DB::transactionLevel());
            DB::setDefaultConnection('passkey_login_competitor');
            try {
                app(DeletePasskey::class)($user, $passkey);
                $removed = true;
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $this->postJson(route('passkey.login'), ['credential' => $fixture->browserAssertion($options, $user->getPasskeyUserHandle())])
                ->assertUnprocessable();
            self::assertTrue($removed);
            $this->assertGuest();
            $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
            self::assertSame(0, AccountSession::query()->count());
            self::assertSame(0, DB::table('audit_events')->where('event', 'auth.login')->count());
            self::assertFalse(session()->has('accounts.recent_authentication_at'));
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('passkey_login_competitor');
        }
    }

    public function test_invalid_registration_origin_returns_credential_feedback_without_persisting_a_passkey(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['accounts.recent_authentication_at' => now()->timestamp])
            ->getJson(route('passkey.registration-options'))->assertOk();
        $options = WebAuthn::fromJson((string) session('passkey.registration_options'), PublicKeyCredentialCreationOptions::class);
        $this->withCredentials()->withCookie((string) config('session.cookie'), session()->getId())
            ->postJson(route('passkey.store'), ['name' => 'Rejected registration', 'credential' => WebAuthnRegistrationFixture::browserCredential(
                $options, origin: 'https://foreign.example.test')])
            ->assertUnprocessable()->assertJsonValidationErrors('credential');

        self::assertSame(0, AccountPasskey::query()->count());
        self::assertSame(0, DB::table('audit_events')->where('event', 'account.passkey.registered')->count());
    }

    /** @return array{User,AccountPasskey,WebAuthnAssertionFixture} */
    private function account(bool $mfa = false, bool $password = true): array
    {
        $user = ($password ? User::factory() : User::factory()->withoutPassword())->create([
            'remember_token' => null,
            'two_factor_secret' => $mfa ? app(TotpService::class)->generateSecret() : null,
            'two_factor_confirmed_at' => $mfa ? now() : null,
            'two_factor_recovery_codes' => $mfa ? [hash('sha256', 'unused-recovery-code')] : null,
        ]);
        $fixture = WebAuthnAssertionFixture::create();
        $options = app(GenerateRegistrationOptions::class)($user);
        $passkey = app(StorePasskey::class)($user, 'Login key', $fixture->registration($options), $options);
        self::assertInstanceOf(AccountPasskey::class, $passkey);

        return [$user, $passkey, $fixture];
    }

    private function verificationOptions(): PublicKeyCredentialRequestOptions
    {
        $this->getJson(route('passkey.login-options'))->assertOk();
        $this->withCredentials()->withCookie((string) config('session.cookie'), session()->getId());

        return WebAuthn::fromJson((string) session('passkey.verification_options'), PublicKeyCredentialRequestOptions::class);
    }

    private function observeRotation(Closure $callback): void
    {
        $store = app('session.store');
        $store->setHandler(new class($callback) extends ArraySessionHandler
        {
            public function __construct(private readonly Closure $callback)
            {
                parent::__construct(120);
            }

            public function destroy($sessionId): bool
            {
                ($this->callback)();

                return parent::destroy($sessionId);
            }
        });
        // Preserve the options established by the preceding real HTTP request.
        $store->save();
    }
}
