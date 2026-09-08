<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Authentication\Actions\RecordAccountSession;
use App\Contexts\Accounts\Authentication\Actions\RevokeAccountSession;
use App\Contexts\Accounts\Authentication\Actions\RevokeOtherAccountSessions;
use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use SessionHandlerInterface;
use Tests\v3\TestCase;

final class CredentialSessionCommitV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{bool}> */
    public static function revocations(): iterable
    {
        yield 'single session' => [false];
        yield 'other sessions' => [true];
    }

    #[DataProvider('revocations')]
    public function test_storage_cleanup_waits_for_the_outer_owner_commit(bool $allOthers): void
    {
        $user = User::factory()->create();
        $record = $this->register($user, 'other-session');
        $destroyed = [];
        $manager = $this->manager(function (string $sessionId) use ($record, &$destroyed): bool {
            self::assertSame(0, DB::transactionLevel());
            self::assertNotNull($record->refresh()->revoked_at);
            $destroyed[] = $sessionId;

            return true;
        });

        DB::transaction(function () use ($allOthers, $manager, $user, $record, &$destroyed): void {
            $this->revoke($allOthers, $manager, $user, $record);
            self::assertNotNull($record->refresh()->revoked_at);
            self::assertSame([], $destroyed);
        });

        self::assertSame(['other-session'], $destroyed);
    }

    #[DataProvider('revocations')]
    public function test_outer_rollback_preserves_session_and_discards_storage_cleanup(bool $allOthers): void
    {
        $user = User::factory()->create();
        $record = $this->register($user, 'other-session');
        $rememberToken = $user->getRememberToken();
        $destroyed = [];
        $manager = $this->manager(static function (string $sessionId) use (&$destroyed): bool {
            $destroyed[] = $sessionId;

            return true;
        });

        try {
            DB::transaction(function () use ($allOthers, $manager, $user, $record): void {
                $this->revoke($allOthers, $manager, $user, $record);
                throw new RuntimeException('Later credential effect failed.');
            });
            self::fail('Exercise the outer rollback.');
        } catch (RuntimeException $exception) {
            self::assertSame('Later credential effect failed.', $exception->getMessage());
        }

        self::assertSame([], $destroyed);
        self::assertNull($record->refresh()->revoked_at);
        self::assertSame($rememberToken, $user->refresh()->getRememberToken());
        self::assertSame(0, DB::table('audit_events')->count());
    }

    #[DataProvider('revocations')]
    public function test_storage_exception_does_not_turn_committed_revocation_into_a_failed_request(bool $allOthers): void
    {
        $user = User::factory()->create();
        $record = $this->register($user, 'other-session');
        $attempts = 0;
        $manager = $this->manager(static function (string $sessionId) use (&$attempts): bool {
            self::assertSame(0, DB::transactionLevel());
            $attempts++;
            throw new RuntimeException('Injected raw session cleanup failure.');
        });

        $this->revoke($allOthers, $manager, $user, $record);

        self::assertSame(1, $attempts);
        self::assertNotNull($record->refresh()->revoked_at);
        self::assertFalse(app(RecordAccountSession::class)->handle((int) $user->id, 'other-session', 'Firefox/'));
    }

    public function test_password_change_writes_one_hash_and_preserves_only_the_current_browser(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $currentId = session()->getId();
        $other = $this->register($user, 'other-browser');
        $passwordWrites = [];
        DB::listen(static function (QueryExecuted $query) use (&$passwordWrites): void {
            if (str_starts_with($query->sql, 'update "users" set') && str_contains($query->sql, '"password" =')) {
                $passwordWrites[] = $query->connection->transactionLevel();
            }
        });

        $this->withCookie((string) config('session.cookie'), $currentId)
            ->put('/profile/password', [
                'current_password' => 'password',
                'password' => 'ChangedPassword123!',
                'password_confirmation' => 'ChangedPassword123!',
            ])->assertRedirect(route('profile.show'))
            ->assertSessionMissing('accounts.recent_authentication_at');

        self::assertSame([1], $passwordWrites);
        self::assertTrue(Hash::check('ChangedPassword123!', (string) $user->refresh()->password));
        self::assertNotNull($other->refresh()->revoked_at);
        self::assertFalse(app(RecordAccountSession::class)->handle((int) $user->id, 'other-browser', 'Firefox/'));
        self::assertNull(AccountSession::query()->where('session_id_hash', hash('sha256', $currentId))->sole()->revoked_at);
        Auth::forgetGuards();
        $this->withCookie((string) config('session.cookie'), $currentId)->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    private function register(User $user, string $sessionId): AccountSession
    {
        self::assertTrue(app(RecordAccountSession::class)->handle((int) $user->id, $sessionId, 'Chrome/'));

        return AccountSession::query()->where('user_id', $user->id)->where('session_id_hash', hash('sha256', $sessionId))->sole();
    }

    private function revoke(bool $allOthers, SessionManager $manager, User $user, AccountSession $record): void
    {
        if ($allOthers) {
            self::assertSame(1, (new RevokeOtherAccountSessions($manager, app(AuditRecorder::class)))->handle((int) $user->id, 'current-session'));
        } else {
            (new RevokeAccountSession($manager, app(AuditRecorder::class)))->handle((int) $user->id, $record->public_id, 'current-session');
        }
    }

    /** @param callable(string):bool $destroy */
    private function manager(callable $destroy): SessionManager
    {
        $handler = Mockery::mock(SessionHandlerInterface::class);
        $handler->shouldReceive('destroy')->andReturnUsing($destroy);
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getHandler')->andReturn($handler);
        $manager = Mockery::mock(SessionManager::class);
        $manager->shouldReceive('driver')->andReturn($store);

        return $manager;
    }
}
