<?php

declare(strict_types=1);

namespace Tests\Integration\Concurrency\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Authentication\Actions\RecordAccountSession;
use App\Contexts\Accounts\Authentication\Actions\RestoreAccountSession;
use App\Contexts\Accounts\Authentication\Actions\RevokeOtherAccountSessions;
use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TotpService;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TwoFactorManager;
use App\Contexts\Accounts\Profile\Actions\ChangePassword;
use Closure;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class RememberedAccountSessionV3Test extends TestCase
{
    use DatabaseTruncation;

    public function test_session_admission_returns_only_a_scalar_account_identity(): void
    {
        $request = Request::create('/profile');
        $request->setLaravelSession(app('session.store'));
        $request->session()->start();
        $admit = app(RestoreAccountSession::class);
        self::assertNull($admit->handle($request));

        $user = User::factory()->create();
        $this->actingAs($user);
        self::assertSame((int) $user->id, $admit->handle($request));
        self::assertSame(1, AccountSession::query()->where('user_id', $user->id)->count());
    }

    /** @return iterable<string,array{string}> */
    public static function accounts(): iterable
    {
        foreach (['password', 'provider-only', 'MFA'] as $kind) {
            yield $kind => [$kind];
        }
    }

    #[DataProvider('accounts')]
    public function test_real_http_recaller_commits_session_and_audit_before_login_event_without_fresh_proof(string $kind): void
    {
        $user = ($kind === 'provider-only' ? User::factory()->google() : User::factory())->create([
            'two_factor_secret' => $kind === 'MFA' ? app(TotpService::class)->generateSecret() : null,
            'two_factor_confirmed_at' => $kind === 'MFA' ? now() : null,
            'two_factor_recovery_codes' => $kind === 'MFA' ? [hash('sha256', 'unused-code')] : null,
        ]);
        [$name, $cookie] = $this->recaller($user);
        $logins = 0;
        $rotations = 0;
        $this->observeRotation(static function () use (&$rotations): void {
            self::assertSame(0, DB::transactionLevel());
            self::assertSame(0, AccountSession::query()->count());
            $rotations++;
        });
        Event::listen(Login::class, static function (Login $event) use (&$logins): void {
            self::assertSame(0, DB::transactionLevel());
            self::assertTrue($event->remember);
            self::assertSame(1, AccountSession::query()->whereNull('revoked_at')->count());
            self::assertSame(1, DB::table('audit_events')->where('event', 'auth.login')->count());
            $logins++;
        });
        $this->withSession(['accounts.recent_authentication_at' => now()->timestamp,
            'accounts.recent_authentication_method' => 'password', 'accounts.mfa_login' => ['obsolete' => true]])
            ->withCookie($name, $cookie)->get('/profile')->assertOk()
            ->assertSessionMissing('accounts.recent_authentication_at')
            ->assertSessionMissing('accounts.mfa_login');

        $this->assertAuthenticatedAs($user);
        self::assertSame(1, $rotations);
        self::assertSame(1, $logins);
        self::assertSame($kind === 'MFA' ? [hash('sha256', 'unused-code')] : null, $user->refresh()->two_factor_recovery_codes);
        $metadata = json_decode((string) DB::table('audit_events')->where('event', 'auth.login')->sole()->metadata, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('remember', $metadata['provider']);
        self::assertNull($metadata['mfa_method']);

        // A normal following request uses the registered session, not a new login.
        $sessionId = session()->getId();
        Auth::forgetGuards();
        $this->withCookie((string) config('session.cookie'), $sessionId)->get('/profile')->assertOk();
        self::assertSame(1, $logins);
        self::assertSame(1, AccountSession::query()->count());
    }

    /** @return iterable<string,array{string}> */
    public static function failures(): iterable
    {
        foreach (['audit', 'session row', 'raw rotation'] as $failure) {
            yield $failure => [$failure];
        }
    }

    #[DataProvider('failures')]
    public function test_failed_remembered_completion_grants_no_events_or_session_and_can_retry(string $failure): void
    {
        $user = User::factory()->create();
        [$name, $cookie] = $this->recaller($user);
        $token = $user->getRememberToken();
        $failed = false;
        $events = 0;
        Event::listen([Login::class, Authenticated::class], static function () use (&$events): void {
            $events++;
        });
        $this->observeRotation(static function () use ($failure, &$failed): void {
            self::assertSame(0, DB::transactionLevel());
            if (! $failed && $failure === 'raw rotation') {
                $failed = true;
                throw new RuntimeException('Injected remembered completion failure.');
            }
        });
        DB::listen(static function (QueryExecuted $query) use ($failure, &$failed): void {
            $target = $failure === 'session row' ? str_starts_with($query->sql, 'insert into "account_sessions"')
                : $failure === 'audit' && str_starts_with($query->sql, 'insert into "audit_events"') && in_array('auth.login', $query->bindings, true);
            if (! $failed && $target) {
                $failed = true;
                throw new RuntimeException('Injected remembered completion failure.');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->withCookie($name, $cookie)->get('/profile');
            self::fail('The injected persistence/storage failure must abort remembered login.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected remembered completion failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        $this->assertGuest();
        self::assertSame(0, $events);
        self::assertSame(0, AccountSession::query()->count());
        self::assertSame(0, DB::table('audit_events')->where('event', 'auth.login')->count());
        self::assertSame($token, $user->refresh()->getRememberToken());
        self::assertFalse(session()->has('accounts.recent_authentication_at'));

        Auth::forgetGuards();
        $this->withCookie($name, $cookie)->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($user);
        self::assertSame(1, $events);
        self::assertSame(1, AccountSession::query()->count());
    }

    /** @return iterable<string,array{string}> */
    public static function transitions(): iterable
    {
        foreach (['password changed', 'remember revoked', 'finalized', 'MFA enabled'] as $transition) {
            yield $transition => [$transition];
        }
    }

    #[DataProvider('transitions')]
    public function test_real_competing_transition_during_recaller_rotation_cannot_register_stale_authentication(string $transition): void
    {
        $user = User::factory()->create();
        [$name, $cookie] = $this->recaller($user);
        $primary = $this->competitor();
        $changed = false;
        $events = 0;
        Event::listen([Login::class, Authenticated::class], static function () use (&$events): void {
            $events++;
        });
        $this->observeRotation(static function () use ($user, $primary, $transition, &$changed): void {
            self::assertSame(0, DB::transactionLevel());
            $broker = Password::getFacadeRoot();
            DB::setDefaultConnection('remembered_competitor');
            Password::swap(new PasswordBrokerManager(app()));
            try {
                if ($transition === 'password changed') {
                    app(ChangePassword::class)->handle((int) $user->id, 'password', 'ChangedPassword123!', null);
                } elseif ($transition === 'remember revoked') {
                    app(RevokeOtherAccountSessions::class)->handle((int) $user->id, null);
                } elseif ($transition === 'finalized') {
                    app(AnonymizeAccount::class)->handle((int) $user->id, 'remembered-rotation-finalization');
                } else {
                    $setup = app(TwoFactorManager::class)->begin($user);
                    $code = app(TotpService::class)->codeForCounter($setup['secret'], intdiv(time(), 30));
                    app(TwoFactorManager::class)->confirm($user, $code);
                }
                $changed = true;
            } finally {
                DB::setDefaultConnection($primary);
                Password::swap($broker);
            }
        });
        try {
            $this->withCookie($name, $cookie)->get('/profile')->assertRedirect(route('login'));
            self::assertTrue($changed);
            $this->assertGuest();
            self::assertSame(0, $events);
            self::assertSame(0, AccountSession::query()->count());
            self::assertSame(0, DB::table('audit_events')->where('event', 'auth.login')->count());
            self::assertFalse(session()->has('accounts.recent_authentication_at'));
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('remembered_competitor');
        }
    }

    public function test_revocation_waiting_behind_remembered_completion_sees_the_new_session(): void
    {
        $user = User::factory()->create();
        [$name, $cookie] = $this->recaller($user);
        $primary = $this->competitor();
        $attempted = false;
        $blocked = false;
        DB::listen(static function (QueryExecuted $query) use ($user, $primary, &$attempted, &$blocked): void {
            if ($attempted || $query->connectionName !== $primary || ! str_contains($query->sql, '"users"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('remembered_competitor');
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
            $this->withCookie($name, $cookie)->get('/profile')->assertOk();
            self::assertTrue($attempted && $blocked);
            $session = AccountSession::query()->sole();
            self::assertSame(1, app(RevokeOtherAccountSessions::class)->handle((int) $user->id, null));
            self::assertNotNull($session->refresh()->revoked_at);
            self::assertFalse(app(RecordAccountSession::class)->handle((int) $user->id, (string) $session->session_id, 'Browser/'));
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('remembered_competitor');
        }
    }

    /** @return iterable<string,array{string}> */
    public static function invalidCookies(): iterable
    {
        foreach (['token', 'password hash', 'shape'] as $invalid) {
            yield $invalid => [$invalid];
        }
    }

    #[DataProvider('invalidCookies')]
    public function test_maintained_recaller_validation_rejects_invalid_cookies_before_registration(string $invalid): void
    {
        $user = User::factory()->create();
        [$name, $cookie] = $this->recaller($user);
        $parts = explode('|', $cookie);
        $cookie = match ($invalid) {
            'token' => $parts[0].'|incorrect-token|'.$parts[2],
            'password hash' => $parts[0].'|'.$parts[1].'|incorrect-hash',
            default => 'malformed',
        };
        $this->withCookie($name, $cookie)->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
        self::assertSame(0, AccountSession::query()->count());
        self::assertSame(0, DB::table('audit_events')->where('event', 'auth.login')->count());
    }

    public function test_recaller_in_an_enclosing_transaction_is_rejected_before_raw_rotation(): void
    {
        $user = User::factory()->create();
        [$name, $cookie] = $this->recaller($user);
        $rotations = 0;
        $this->observeRotation(static function () use (&$rotations): void {
            $rotations++;
        });
        $this->withoutExceptionHandling();
        try {
            DB::transaction(fn () => $this->withCookie($name, $cookie)->get('/profile'));
            self::fail('An enclosing transaction must be rejected before maintained restoration.');
        } catch (LogicException $exception) {
            self::assertSame('Remembered account restoration must run outside an enclosing database transaction.', $exception->getMessage());
        }
        self::assertSame(0, $rotations);
        self::assertSame(0, AccountSession::query()->count());
    }

    /** @return array{string,string} */
    private function recaller(User $user): array
    {
        $guard = Auth::guard('web');

        return [$guard->getRecallerName(), $user->id.'|'.$user->getRememberToken().'|'.$guard->hashPasswordForCookie($user->getAuthPassword())];
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.remembered_competitor', array_replace(DB::connection()->getConfig(), ['name' => 'remembered_competitor']));
        DB::connection('remembered_competitor')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    private function observeRotation(Closure $callback): void
    {
        app('session.store')->setHandler(new class($callback) extends ArraySessionHandler
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
