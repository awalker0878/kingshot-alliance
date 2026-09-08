<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Authentication\Actions\LogoutAccount;
use App\Contexts\Accounts\Authentication\Actions\RecordAccountSession;
use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\v3\TestCase;

final class AccountLogoutV3Test extends TestCase
{
    use DatabaseMigrations;

    public function test_logout_revokes_current_session_and_remembered_authority_but_preserves_other_active_sessions(): void
    {
        $user = User::factory()->create();
        $rememberToken = $user->getRememberToken();
        $this->actingAs($user)->get('/profile')->assertOk();
        $currentSessionId = session()->getId();
        self::assertTrue(app(RecordAccountSession::class)->handle(
            (int) $user->id,
            'another-browser-session',
            'Firefox/',
        ));

        $events = 0;
        Event::listen(CurrentDeviceLogout::class, static function () use (&$events): void {
            self::assertSame(0, DB::transactionLevel());
            self::assertFalse(Auth::check());
            self::assertSame(1, DB::table('audit_events')->where('event', 'auth.logout')->count());
            $events++;
        });
        $this->withCookie((string) config('session.cookie'), $currentSessionId)
            ->delete(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
        self::assertNotSame($currentSessionId, session()->getId());
        self::assertNotNull(AccountSession::query()
            ->where('session_id_hash', hash('sha256', $currentSessionId))->sole()->revoked_at);
        self::assertNull(AccountSession::query()
            ->where('session_id_hash', hash('sha256', 'another-browser-session'))->sole()->revoked_at);
        self::assertNotSame($rememberToken, $user->refresh()->getRememberToken());
        self::assertSame(1, $events);
        self::assertSame(1, DB::table('audit_events')->where('event', 'auth.logout')->count());
    }

    public function test_audit_failure_rolls_back_revocation_but_still_clears_the_current_browser(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile')->assertOk();
        $currentSessionId = session()->getId();
        $rememberToken = $user->getRememberToken();
        $events = 0;
        Event::listen(CurrentDeviceLogout::class, static function () use (&$events): void {
            $events++;
        });
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "audit_events"')
                && in_array('auth.logout', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected logout audit failure.');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->withCookie((string) config('session.cookie'), $currentSessionId)->delete(route('logout'));
            self::fail('The injected audit failure must abort the durable logout transaction.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected logout audit failure.', $exception->getMessage());
        }

        self::assertTrue($failed);
        $this->assertGuest();
        self::assertNotSame($currentSessionId, session()->getId());
        self::assertNull(AccountSession::query()->sole()->revoked_at);
        self::assertSame($rememberToken, $user->refresh()->getRememberToken());
        self::assertSame(0, $events);
        self::assertSame(0, DB::table('audit_events')->where('event', 'auth.logout')->count());
    }

    /** @return iterable<string,array{bool}> */
    public static function credentialKinds(): iterable
    {
        yield 'password' => [false];
        yield 'provider only' => [true];
    }

    #[DataProvider('credentialKinds')]
    public function test_copied_remember_cookie_cannot_restore_access_after_logout(bool $providerOnly): void
    {
        $user = ($providerOnly ? User::factory()->google() : User::factory())->create();
        $guard = Auth::guard('web');
        $name = $guard->getRecallerName();
        $cookie = $user->id.'|'.$user->getRememberToken().'|'.$guard->hashPasswordForCookie($user->getAuthPassword());
        $this->withCookie($name, $cookie)->get('/profile')->assertOk();
        $originalId = session()->getId();
        $this->withCookie((string) config('session.cookie'), $originalId)
            ->delete(route('logout'))->assertRedirect(route('home'));

        Auth::forgetGuards();
        $this->withCookie((string) config('session.cookie'), session()->getId())
            ->withCookie($name, $cookie)->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
        self::assertSame(1, AccountSession::query()->count());
        self::assertNotNull(AccountSession::query()->sole()->revoked_at);
    }

    /** @return iterable<string,array{bool}> */
    public static function storageFailures(): iterable
    {
        yield 'false return' => [false];
        yield 'exception' => [true];
    }

    #[DataProvider('storageFailures')]
    public function test_failed_raw_cleanup_rotates_browser_and_durable_marker_rejects_stale_storage(bool $throws): void
    {
        $handler = new class($throws) extends ArraySessionHandler
        {
            public int $destroyCalls = 0;

            public function __construct(private readonly bool $throws)
            {
                parent::__construct(120);
            }

            public function destroy($sessionId): bool
            {
                $this->destroyCalls++;
                AccountLogoutV3Test::assertSame(0, DB::transactionLevel());
                if ($this->throws) {
                    throw new RuntimeException('Injected logout storage failure.');
                }

                return false;
            }
        };
        app('session.store')->setHandler($handler);
        $user = User::factory()->create();
        $this->actingAs($user)->withSession([
            'accounts.recent_authentication_at' => now()->timestamp,
            'accounts.mfa_login' => ['obsolete' => true],
        ])->get('/profile')->assertOk();
        $originalId = session()->getId();
        // ActingAs only seeds the in-memory guard; reproduce the real persisted
        // authentication key so stale raw storage can be replayed after logout.
        session()->put(Auth::guard('web')->getName(), $user->id);
        session()->save();

        $this->withCookie((string) config('session.cookie'), $originalId)
            ->delete(route('logout'))->assertRedirect(route('home'))
            ->assertSessionMissing('accounts.recent_authentication_at')
            ->assertSessionMissing('accounts.mfa_login');
        $this->assertGuest();
        self::assertSame(1, $handler->destroyCalls);
        self::assertNotSame($originalId, session()->getId());
        self::assertNotNull(AccountSession::query()->sole()->revoked_at);

        Auth::forgetGuards();
        $this->withCookie((string) config('session.cookie'), $originalId)
            ->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
        self::assertSame(1, AccountSession::query()->count());
    }

    public function test_failing_logout_listener_cannot_interrupt_clearing_recent_proof_or_guard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['accounts.recent_authentication_at' => now()->timestamp])
            ->get('/profile')->assertOk();
        $originalId = session()->getId();
        Event::listen(CurrentDeviceLogout::class, static function (): void {
            throw new RuntimeException('Injected logout listener failure.');
        });

        $this->withCookie((string) config('session.cookie'), $originalId)
            ->delete(route('logout'))->assertRedirect(route('home'))
            ->assertSessionMissing('accounts.recent_authentication_at');
        $this->assertGuest();
        self::assertNotSame($originalId, session()->getId());
        self::assertNotNull(AccountSession::query()->sole()->revoked_at);
    }

    public function test_enclosing_transaction_is_rejected_without_mutating_browser_or_durable_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile')->assertOk();
        $originalId = session()->getId();
        try {
            DB::transaction(fn () => app(LogoutAccount::class)->handle(request()));
            self::fail('Logout must not span external session storage with a database transaction.');
        } catch (LogicException $exception) {
            self::assertSame('Account logout must run outside an enclosing database transaction.', $exception->getMessage());
        }
        $this->assertAuthenticatedAs($user);
        self::assertSame($originalId, session()->getId());
        self::assertNull(AccountSession::query()->sole()->revoked_at);
        self::assertSame(0, DB::table('audit_events')->where('event', 'auth.logout')->count());
    }
}
