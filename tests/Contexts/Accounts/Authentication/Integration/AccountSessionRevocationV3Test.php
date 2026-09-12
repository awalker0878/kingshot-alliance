<?php

declare(strict_types=1);

namespace Tests\Contexts\Accounts\Authentication\Integration;

use App\Contexts\Accounts\Authentication\Actions\RecordAccountSession;
use App\Contexts\Accounts\Authentication\Actions\RevokeAccountSession;
use App\Contexts\Accounts\Authentication\Actions\RevokeOtherAccountSessions;
use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use SessionHandlerInterface;
use Tests\TestCase;

final class AccountSessionRevocationV3Test extends TestCase
{
    use DatabaseTruncation;

    public function test_completed_login_registers_its_rotated_session_before_the_redirect_is_returned(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $record = AccountSession::query()->where('user_id', $user->id)->sole();
        self::assertSame(hash('sha256', session()->getId()), $record->session_id_hash);
        self::assertNull($record->revoked_at);
    }

    public function test_failed_storage_delete_and_stale_tracking_cannot_restore_revoked_access(): void
    {
        $user = User::factory()->google()->create();
        $this->actingAs($user)
            ->withSession(['accounts.recent_authentication_at' => now()->timestamp])
            ->get('/profile')->assertOk();
        $record = AccountSession::query()->where('user_id', $user->id)->sole();
        $originalSessionId = (string) $record->session_id;

        $revoke = new RevokeAccountSession($this->failingSessionManager(), app(AuditRecorder::class));
        $revoke->handle((int) $user->id, $record->public_id, 'another-browser-session');
        $revokedAt = $record->refresh()->revoked_at;
        self::assertNotNull($revokedAt);

        self::assertFalse(app(RecordAccountSession::class)->handle(
            (int) $user->id, $originalSessionId, 'Firefox/',
        ));
        self::assertTrue($record->refresh()->revoked_at?->equalTo($revokedAt));
        self::assertSame('Browser', $record->browser_family);

        $this->withCookie((string) config('session.cookie'), $originalSessionId)->get('/profile')
            ->assertRedirect(route('login'))
            ->assertSessionMissing('accounts.recent_authentication_at');
        $this->assertGuest();
        self::assertNotSame($originalSessionId, session()->getId());
        self::assertSame(1, AccountSession::query()->where('user_id', $user->id)->count());
    }

    public function test_revocation_between_tracking_read_and_write_is_enforced_by_the_write_predicate(): void
    {
        $user = User::factory()->create();
        $record = $this->register($user, 'in-flight-session');
        $revoke = new RevokeAccountSession($this->failingSessionManager(), app(AuditRecorder::class));
        $intercepted = false;

        DB::listen(static function (QueryExecuted $query) use ($record, $revoke, &$intercepted): void {
            if ($intercepted || ! str_starts_with($query->sql, 'select')
                || ! str_contains($query->sql, '"account_sessions"')
                || ! in_array($record->session_id_hash, $query->bindings, true)) {
                return;
            }

            $intercepted = true;
            $revoke->handle((int) $record->user_id, $record->public_id, 'current-session');
        });

        self::assertFalse(app(RecordAccountSession::class)->handle(
            (int) $user->id, 'in-flight-session', 'Firefox/',
        ));
        self::assertTrue($intercepted);
        self::assertNotNull($record->refresh()->revoked_at);
        self::assertSame('Chrome', $record->browser_family);
    }

    public function test_revoke_others_crosses_batches_but_preserves_current_and_foreign_sessions(): void
    {
        $user = User::factory()->google()->create();
        $otherUser = User::factory()->create();
        $current = $this->register($user, 'current-session');
        $foreign = $this->register($otherUser, 'foreign-session');
        $oldToken = $user->getRememberToken();
        $foreignToken = $otherUser->getRememberToken();

        for ($i = 0; $i < 101; $i++) {
            $this->register($user, 'other-session-'.$i);
        }

        $revoke = new RevokeOtherAccountSessions($this->failingSessionManager(), app(AuditRecorder::class));
        self::assertSame(101, $revoke->handle((int) $user->id, 'current-session'));
        self::assertSame(101, AccountSession::query()->where('user_id', $user->id)->whereNotNull('revoked_at')->count());
        self::assertNull($current->refresh()->revoked_at);
        self::assertNull($foreign->refresh()->revoked_at);
        self::assertNotSame($oldToken, $user->refresh()->getRememberToken());
        self::assertSame($foreignToken, $otherUser->refresh()->getRememberToken());
        self::assertSame(0, $revoke->handle((int) $user->id, 'current-session'));
        self::assertTrue(app(RecordAccountSession::class)->handle((int) $user->id, 'current-session', 'Chrome/'));
    }

    #[DataProvider('rememberedRevocations')]
    public function test_revoked_remembered_sign_in_cannot_create_a_replacement_session(bool $google, bool $allOthers): void
    {
        $user = $google ? User::factory()->google()->create() : User::factory()->create();
        $guard = Auth::guard('web');
        $cookieName = $guard->getRecallerName();
        $cookie = $user->id.'|'.$user->getRememberToken().'|'.$guard->hashPasswordForCookie((string) $user->getAuthPassword());

        // Exercise a valid remembered sign-in before revoking its resulting session.
        $this->withCookie($cookieName, $cookie)->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($user);
        $record = AccountSession::query()->where('user_id', $user->id)->sole();

        if ($allOthers) {
            app(RevokeOtherAccountSessions::class)->handle((int) $user->id, 'another-browser-session');
        } else {
            app(RevokeAccountSession::class)->handle((int) $user->id, $record->public_id, 'another-browser-session');
        }

        session()->invalidate();
        Auth::forgetGuards();
        $this->withCookie($cookieName, $cookie)->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
        self::assertNotNull($record->refresh()->revoked_at);
        self::assertSame(1, AccountSession::query()->where('user_id', $user->id)->count());
    }

    /** @return array<string, array{bool, bool}> */
    public static function rememberedRevocations(): array
    {
        return [
            'password, one session' => [false, false],
            'password, other sessions' => [false, true],
            'Google, one session' => [true, false],
            'Google, other sessions' => [true, true],
        ];
    }

    public function test_current_session_cannot_be_revoked_by_the_single_session_route(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->withSession(['accounts.recent_authentication_at' => now()->timestamp])
            ->get('/profile')->assertOk();
        $record = AccountSession::query()->where('user_id', $user->id)->sole();
        $token = $user->getRememberToken();

        $this->withCookie((string) config('session.cookie'), (string) $record->session_id)
            ->delete('/profile/security/sessions/'.$record->public_id)->assertSessionHasErrors('session');

        self::assertNull($record->refresh()->revoked_at);
        self::assertSame($token, $user->refresh()->getRememberToken());
        $this->assertAuthenticatedAs($user);
    }

    public function test_anonymized_account_cannot_re_register_a_stale_session(): void
    {
        $user = User::factory()->google()->create();
        $this->actingAs($user)->get('/profile')->assertOk();
        app(AnonymizeAccount::class)->handle((int) $user->id, 'session-revocation-test');

        $this->actingAs($user->refresh())->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
        self::assertSame(0, AccountSession::query()->where('user_id', $user->id)->count());
    }

    private function register(User $user, string $sessionId): AccountSession
    {
        self::assertTrue(app(RecordAccountSession::class)->handle((int) $user->id, $sessionId, 'Chrome/'));

        return AccountSession::query()->where('user_id', $user->id)
            ->where('session_id_hash', hash('sha256', $sessionId))->sole();
    }

    private function failingSessionManager(): SessionManager
    {
        $handler = Mockery::mock(SessionHandlerInterface::class);
        $handler->shouldReceive('destroy')->andReturn(false);
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getHandler')->andReturn($handler);
        $manager = Mockery::mock(SessionManager::class);
        $manager->shouldReceive('driver')->andReturn($store);

        return $manager;
    }
}
