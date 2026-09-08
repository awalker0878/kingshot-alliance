<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Authentication\Actions\AuthenticateWithGoogle;
use App\Contexts\Accounts\Authentication\Actions\AuthenticateWithPassword;
use App\Contexts\Accounts\Authentication\Actions\RecordAccountSession;
use App\Contexts\Accounts\Authentication\Actions\RevokeOtherAccountSessions;
use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Credentials\Actions\RemovePassword;
use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Actions\RemoveAccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\MultiFactorAuthentication\Actions\CompleteMfaLogin;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TotpService;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TwoFactorManager;
use App\Contexts\Accounts\Profile\Actions\ChangePassword;
use Closure;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\v3\TestCase;

final class AccountLoginCompletionV3Test extends TestCase
{
    use DatabaseMigrations;

    private const RECOVERY = 'a1b2-c3d4-e5f6-0123';

    protected function setUp(): void
    {
        parent::setUp();
        $client = hash('sha256', self::class.'::'.$this->nameWithDataSet());
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::'.substr($client, 0, 4).':'.substr($client, 4, 4)]);
    }

    public function test_real_password_http_login_registers_the_rotated_session_before_login_events_and_initializes_remember_atomically(): void
    {
        $user = User::factory()->create(['remember_token' => null]);
        $rotations = 0;
        $store = app('session.store');
        $this->observeRotation($store, static function () use (&$rotations): void {
            self::assertSame(0, DB::transactionLevel());
            self::assertSame(0, AccountSession::query()->count());
            self::assertSame(0, DB::table('audit_events')->where('event', 'auth.login')->count());
            $rotations++;
        });
        $events = [];
        foreach ([Login::class, Authenticated::class] as $eventType) {
            Event::listen($eventType, static function ($event) use (&$events, $user): void {
                self::assertSame(0, DB::transactionLevel());
                self::assertSame((int) $user->id, (int) $event->user->id);
                self::assertSame(1, AccountSession::query()->where('user_id', $user->id)->whereNull('revoked_at')->count());
                self::assertSame(1, DB::table('audit_events')->where('event', 'auth.login')->count());
                $events[] = $event::class;
            });
        }
        $writes = [];
        DB::listen(static function (QueryExecuted $query) use (&$writes): void {
            if (str_starts_with($query->sql, 'update "users" set') && str_contains($query->sql, '"remember_token"')) {
                $writes[] = $query->connection->transactionLevel();
            }
        });

        $this->post('/login', ['email' => strtoupper((string) $user->email), 'password' => 'password', 'remember' => true])
            ->assertRedirect(route('dashboard'))->assertSessionHas('accounts.recent_authentication_method', 'password');

        $this->assertAuthenticatedAs($user);
        self::assertSame(1, $rotations);
        self::assertSame([Login::class, Authenticated::class], $events);
        self::assertSame([1], $writes);
        self::assertSame(60, strlen((string) $user->refresh()->getRememberToken()));
        self::assertSame(hash('sha256', session()->getId()), AccountSession::query()->sole()->session_id_hash);
    }

    /** @return iterable<string,array{bool,string}> */
    public static function failures(): iterable
    {
        foreach ([false, true] as $mfa) {
            foreach (['audit', 'session row', 'raw rotation'] as $failure) {
                yield ($mfa ? 'recovery ' : 'password ').$failure => [$mfa, $failure];
            }
        }
    }

    #[DataProvider('failures')]
    public function test_failed_preparation_or_commit_leaves_no_login_and_keeps_mfa_recovery_retryable(bool $mfa, string $failure): void
    {
        $user = $this->account($mfa);
        $request = $this->request();
        if ($mfa) {
            self::assertTrue(app(AuthenticateWithPassword::class)->handle($request, (string) $user->email, 'password', true, null));
        }
        $failed = false;
        $rotations = 0;
        $this->observeRotation($request->session(), static function () use ($failure, &$failed, &$rotations): void {
            self::assertSame(0, DB::transactionLevel());
            $rotations++;
            if ($failure === 'raw rotation' && ! $failed) {
                $failed = true;
                throw new RuntimeException('Injected login completion failure.');
            }
        });
        DB::listen(static function (QueryExecuted $query) use ($failure, &$failed): void {
            $target = $failure === 'session row'
                ? str_starts_with($query->sql, 'insert into "account_sessions"')
                : $failure === 'audit' && str_starts_with($query->sql, 'insert into "audit_events"') && in_array('auth.login', $query->bindings, true);
            if (! $failed && $target) {
                $failed = true;
                throw new RuntimeException('Injected login completion failure.');
            }
        });
        $loginEvents = 0;
        $authenticatedEvents = 0;
        Event::listen(Login::class, static function () use (&$loginEvents): void {
            $loginEvents++;
        });
        Event::listen(Authenticated::class, static function () use (&$authenticatedEvents): void {
            $authenticatedEvents++;
        });

        try {
            $this->complete($request, $user, $mfa);
            self::fail('Exercise a real failure in the prepared login or its durable completion.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected login completion failure.', $exception->getMessage());
        }

        self::assertTrue($failed);
        self::assertSame(1, $rotations);
        self::assertSame(0, $loginEvents);
        self::assertSame(0, $authenticatedEvents);
        $this->assertGuest();
        self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
        self::assertSame(0, AccountSession::query()->count());
        self::assertSame(0, DB::table('audit_events')->whereIn('event', ['auth.login', 'auth.mfa.recovery_code_used'])->count());
        self::assertNull($user->refresh()->getRawOriginal('remember_token'));
        $guard = Auth::guard('web');
        self::assertInstanceOf(SessionGuard::class, $guard);
        self::assertFalse(Cookie::hasQueued($guard->getRecallerName()));
        if ($mfa) {
            self::assertTrue($request->session()->has('accounts.mfa_login'));
            self::assertSame([hash('sha256', self::RECOVERY)], $user->two_factor_recovery_codes);
        }

        $this->complete($request, $user, $mfa);
        $this->assertAuthenticatedAs($user);
        self::assertSame(1, $loginEvents);
        self::assertSame(1, $authenticatedEvents);
        self::assertSame(1, AccountSession::query()->count());
        self::assertFalse($request->session()->has('accounts.mfa_login'));
        self::assertSame($mfa ? [] : null, $user->refresh()->two_factor_recovery_codes);
    }

    /** @return iterable<string,array{string,bool}> */
    public static function transitions(): iterable
    {
        foreach (['password changed', 'password removed', 'finalized', 'remember revoked', 'MFA enabled'] as $transition) {
            yield 'password: '.$transition => [$transition, false];
        }
        foreach (['password changed', 'identity removed', 'finalized', 'remember revoked', 'MFA enabled'] as $transition) {
            yield 'Google: '.$transition => [$transition, true];
        }
    }

    #[DataProvider('transitions')]
    public function test_real_competing_transition_during_raw_rotation_cannot_create_a_session_from_earlier_primary_proof(string $transition, bool $google): void
    {
        $user = User::factory()->google()->create(['password' => Hash::make('password')]);
        $identity = $user->accountIdentities()->where('provider', 'google')->sole();
        $request = $this->request();
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.login_transition_competitor', array_replace(DB::connection()->getConfig(), ['name' => 'login_transition_competitor']));
        DB::connection('login_transition_competitor')->statement("SET lock_timeout = '100ms'");
        $transitioned = false;
        $this->observeRotation($request->session(), static function () use ($user, $primary, $transition, &$transitioned): void {
            self::assertSame(0, DB::transactionLevel(), 'Raw storage must never execute while an account lock is held.');
            $broker = Password::getFacadeRoot();
            DB::setDefaultConnection('login_transition_competitor');
            Password::swap(new PasswordBrokerManager(app()));
            try {
                if ($transition === 'password changed') {
                    app(ChangePassword::class)->handle((int) $user->id, 'password', 'ChangedPassword123!', null);
                } elseif ($transition === 'password removed') {
                    app(RemovePassword::class)->handle((int) $user->id, null);
                } elseif ($transition === 'identity removed') {
                    app(RemoveAccountIdentity::class)->handle((int) $user->id, 'google', null);
                } elseif ($transition === 'finalized') {
                    app(AnonymizeAccount::class)->handle((int) $user->id, 'login-rotation-finalization');
                } elseif ($transition === 'remember revoked') {
                    app(RevokeOtherAccountSessions::class)->handle((int) $user->id, null);
                } else {
                    $setup = app(TwoFactorManager::class)->begin($user);
                    $code = app(TotpService::class)->codeForCounter($setup['secret'], intdiv(time(), 30));
                    app(TwoFactorManager::class)->confirm($user, $code);
                }
                $transitioned = true;
            } finally {
                DB::setDefaultConnection($primary);
                Password::swap($broker);
            }
        });

        try {
            try {
                if ($google) {
                    app(AuthenticateWithGoogle::class)->handle($request, (int) $user->id, (int) $identity->id,
                        $identity->provider_subject, (string) $identity->provider_email, null);
                } else {
                    app(AuthenticateWithPassword::class)->handle($request, (string) $user->email, 'password', true, null);
                }
                self::fail('The final account lock must recheck proof after raw rotation.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey($google ? 'google' : 'email', $exception->errors());
            }
            self::assertTrue($transitioned);
            $this->assertGuest();
            self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
            self::assertSame(0, AccountSession::query()->count());
            self::assertSame(0, DB::table('audit_events')->where('event', 'auth.login')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('login_transition_competitor');
        }
    }

    public function test_competing_revocation_cannot_slip_between_final_proof_check_and_session_registration(): void
    {
        $user = $this->account(false);
        $request = $this->request();
        $prepared = false;
        $this->observeRotation($request->session(), static function () use (&$prepared): void {
            self::assertSame(0, DB::transactionLevel());
            $prepared = true;
        });
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.login_completion_competitor', array_replace(DB::connection()->getConfig(), ['name' => 'login_completion_competitor']));
        DB::connection('login_completion_competitor')->statement("SET lock_timeout = '100ms'");
        $attempted = false;
        $blocked = false;
        DB::listen(static function (QueryExecuted $query) use ($user, $primary, &$prepared, &$attempted, &$blocked): void {
            if (! $prepared || $attempted || $query->connectionName !== $primary
                || ! str_contains($query->sql, '"users"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('login_completion_competitor');
            try {
                app(RevokeOtherAccountSessions::class)->handle((int) $user->id, null);
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? null) !== '55P03') {
                    throw $exception;
                }
                $blocked = true;
            } finally {
                DB::setDefaultConnection($primary);
            }
        });

        try {
            app(AuthenticateWithPassword::class)->handle($request, (string) $user->email, 'password', true, null);
            self::assertTrue($attempted && $blocked);
            $session = AccountSession::query()->sole();
            self::assertNull($session->revoked_at);
            // After completion, revocation sees the newly registered session.
            $this->observeRotation($request->session(), static fn () => null);
            self::assertSame(1, app(RevokeOtherAccountSessions::class)->handle((int) $user->id, null));
            self::assertNotNull($session->refresh()->revoked_at);
            self::assertFalse(app(RecordAccountSession::class)->handle((int) $user->id, $request->session()->getId(), 'Browser/'));
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('login_completion_competitor');
        }
    }

    public function test_mfa_challenge_does_not_emit_login_or_rotate_remember_before_a_valid_second_factor(): void
    {
        $user = $this->account(true);
        $request = $this->request();
        $rotations = 0;
        $logins = 0;
        $this->observeRotation($request->session(), static function () use (&$rotations): void {
            $rotations++;
        });
        Event::listen(Login::class, static function () use (&$logins): void {
            $logins++;
        });

        self::assertTrue(app(AuthenticateWithPassword::class)->handle($request, (string) $user->email, 'password', true, 'invitation-token'));
        try {
            app(CompleteMfaLogin::class)->handle($request, 'not-a-code', '');
            self::fail('An invalid second factor must not prepare an authenticated session.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('code', $exception->errors());
        }
        $this->assertGuest();
        self::assertSame(0, $rotations);
        self::assertSame(0, $logins);
        self::assertNull($user->refresh()->getRawOriginal('remember_token'));
        self::assertSame(0, AccountSession::query()->count());
        self::assertSame('invitation-token', session('accounts.mfa_login.invitation_token'));
    }

    public function test_an_enclosing_transaction_is_rejected_before_login_storage_or_events(): void
    {
        $user = $this->account(false);
        $request = $this->request();
        try {
            DB::transaction(static fn () => app(AuthenticateWithPassword::class)->handle($request, (string) $user->email, 'password', true, null));
            self::fail('Login completion must not retain an enclosing transaction across raw storage.');
        } catch (LogicException $exception) {
            self::assertSame('Account login must run outside an enclosing database transaction.', $exception->getMessage());
        }
        $this->assertGuest();
        self::assertSame(0, AccountSession::query()->count());
        self::assertNull($user->refresh()->getRawOriginal('remember_token'));
        self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
    }

    /** @return iterable<string,array{string}> */
    public static function rejectedGoogleProofs(): iterable
    {
        foreach (['wrong account', 'wrong subject', 'disconnected'] as $state) {
            yield $state => [$state];
        }
    }

    #[DataProvider('rejectedGoogleProofs')]
    public function test_google_routing_snapshots_cannot_replace_exact_current_provider_proof(string $state): void
    {
        $user = User::factory()->google()->create(['password' => Hash::make('password')]);
        $identity = $user->accountIdentities()->where('provider', 'google')->sole();
        $request = $this->request();
        $expectedUserId = $state === 'wrong account' ? (int) User::factory()->create()->id : (int) $user->id;
        if ($state === 'disconnected') {
            app(RemoveAccountIdentity::class)->handle((int) $user->id, 'google', null);
        }
        $before = DB::table('audit_events')->count();
        try {
            app(AuthenticateWithGoogle::class)->handle($request, $expectedUserId, (int) $identity->id,
                $state === 'wrong subject' ? 'another-subject' : $identity->provider_subject, 'provider@example.test', null);
            self::fail('Only the current owner and verified provider subject may begin login.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
        $this->assertGuest();
        self::assertSame($before, DB::table('audit_events')->count());
        self::assertSame(0, AccountSession::query()->count());
        self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
    }

    public function test_registration_auto_login_cannot_resolve_the_same_credentials_to_another_account(): void
    {
        $created = $this->account(false);
        $replacement = $this->account(false);
        $request = $this->request();
        try {
            app(AuthenticateWithPassword::class)->handle($request, (string) $replacement->email, 'password', false, null, (int) $created->id);
            self::fail('Registration auto-login is bound to its completed registration result.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('email', $exception->errors());
        }
        $this->assertGuest();
        self::assertSame(0, AccountSession::query()->count());
        self::assertSame(0, DB::table('audit_events')->where('event', 'auth.login')->count());
    }

    /** @return iterable<string,array{bool}> */
    public static function registrationCompletions(): iterable
    {
        yield 'successful auto-login' => [false];
        yield 'late auto-login audit failure' => [true];
    }

    #[DataProvider('registrationCompletions')]
    public function test_registration_http_commits_onboarding_before_safe_auto_login(bool $failLogin): void
    {
        config()->set('accounts.registration_mode', 'open');
        if ($failLogin) {
            $this->withoutExceptionHandling();
            DB::listen(static function (QueryExecuted $query): void {
                if (str_starts_with($query->sql, 'insert into "audit_events"') && in_array('auth.login', $query->bindings, true)) {
                    throw new RuntimeException('Injected auto-login audit failure.');
                }
            });
        }
        try {
            $response = $this->post('/register', [
                'name' => 'Registration fixture', 'email' => 'new-account@example.test',
                'password' => 'RegistrationPassword123!', 'password_confirmation' => 'RegistrationPassword123!',
                'timezone' => 'UTC',
            ]);
            self::assertFalse($failLogin);
            $response->assertRedirect(route('verification.notice'))
                ->assertSessionHas('accounts.recent_authentication_method', 'password');
        } catch (RuntimeException $exception) {
            self::assertTrue($failLogin);
            self::assertSame('Injected auto-login audit failure.', $exception->getMessage());
        }

        $user = User::query()->where('email', 'new-account@example.test')->sole();
        $this->assertDatabaseHas('audit_events', ['event' => 'user.registered', 'actor_user_id' => $user->id]);
        self::assertSame($failLogin ? 0 : 1, AccountSession::query()->where('user_id', $user->id)->count());
        self::assertSame($failLogin ? 0 : 1, DB::table('audit_events')->where('event', 'auth.login')->count());
        if ($failLogin) {
            $this->assertGuest();
            self::assertFalse(session()->has('accounts.recent_authentication_at'));
        } else {
            $this->assertAuthenticatedAs($user);
        }
    }

    private function account(bool $mfa): User
    {
        return User::factory()->create([
            'remember_token' => null,
            'two_factor_secret' => $mfa ? app(TotpService::class)->generateSecret() : null,
            'two_factor_confirmed_at' => $mfa ? now() : null,
            'two_factor_recovery_codes' => $mfa ? [hash('sha256', self::RECOVERY)] : null,
        ]);
    }

    private function request(): Request
    {
        Auth::forgetGuards();
        $request = Request::create('/login', 'POST');
        $request->setLaravelSession(app('session.store'));
        $request->session()->start();
        $request->setUserResolver(static fn () => Auth::user());
        $this->app->instance('request', $request);

        return $request;
    }

    private function complete(Request $request, User $user, bool $mfa): void
    {
        if ($mfa) {
            app(CompleteMfaLogin::class)->handle($request, '', self::RECOVERY);
        } else {
            self::assertFalse(app(AuthenticateWithPassword::class)->handle($request, (string) $user->email, 'password', true, null));
        }
    }

    private function observeRotation(Store $store, Closure $callback): void
    {
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
    }
}
