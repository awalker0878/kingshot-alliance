<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Credentials;

use App\Contexts\Accounts\Authentication\Actions\RecordAccountSession;
use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Credentials\Actions\RemovePassword;
use App\Contexts\Accounts\Credentials\Actions\ResetPassword;
use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\v3\TestCase;

final class PasswordResetAtomicityV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{string}> */
    public static function failures(): iterable
    {
        yield 'security intent' => ['intent'];
        yield 'token consumption' => ['token'];
    }

    #[DataProvider('failures')]
    public function test_late_failure_preserves_password_token_sessions_and_security_effects(string $point): void
    {
        Event::fake([PasswordResetEvent::class]);
        $user = $this->account();
        $token = Password::broker()->createToken($user);
        $before = $this->state($user);
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use ($point, &$failed): void {
            $matches = $point === 'intent'
                ? str_starts_with($query->sql, 'insert into "notification_messages"')
                : str_starts_with($query->sql, 'delete from "password_reset_tokens"');
            if (! $failed && $matches) {
                $failed = true;
                throw new RuntimeException('Injected reset effect failure.');
            }
        });

        try {
            $this->reset($user, $token);
            self::fail('The owner must commit all reset effects and token consumption together.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected reset effect failure.', $exception->getMessage());
        }

        self::assertTrue($failed);
        self::assertSame($before, $this->state($user));
        self::assertTrue(Password::broker()->tokenExists($user, $token));
        Event::assertNotDispatched(PasswordResetEvent::class);
    }

    public function test_successful_reset_consumes_once_and_revokes_all_old_api_browser_and_remember_credentials(): void
    {
        Event::fake([PasswordResetEvent::class]);
        $user = $this->account();
        $remember = $user->getRememberToken();
        $token = Password::broker()->createToken($user);

        self::assertSame(Password::PASSWORD_RESET, $this->reset($user, $token));
        self::assertTrue(Hash::check('Primary-Reset-Password-123!', (string) $user->refresh()->password));
        self::assertNotSame($remember, $user->getRememberToken());
        self::assertSame(0, $user->tokens()->count());
        self::assertNotNull(AccountSession::query()->where('user_id', $user->id)->sole()->revoked_at);
        self::assertFalse(Password::broker()->tokenExists($user, $token));
        $this->assertDatabaseHas('notification_messages', ['recipient_user_id' => $user->id, 'subject_id' => 'auth.password.reset']);
        $state = $this->state($user);

        self::assertSame(Password::INVALID_TOKEN, $this->reset($user, $token));
        self::assertSame($state, $this->state($user));
        Event::assertDispatchedTimes(PasswordResetEvent::class, 1);
    }

    /** @return iterable<string,array{string}> */
    public static function competitors(): iterable
    {
        yield 'second reset' => ['reset'];
        yield 'password removal' => ['removal'];
        yield 'token issuance' => ['issuance'];
    }

    #[DataProvider('competitors')]
    public function test_competing_credential_change_cannot_run_between_token_check_and_reset(string $operation): void
    {
        $user = $this->account();
        $token = Password::broker()->createToken($user);
        $primary = DB::getDefaultConnection();
        $primaryBroker = Password::getFacadeRoot();
        config()->set('database.connections.reset_competitor', array_replace(DB::connection()->getConfig(), ['name' => 'reset_competitor']));
        DB::connection('reset_competitor')->statement("SET lock_timeout = '100ms'");
        $attempted = false;
        $blocked = false;

        DB::listen(function (QueryExecuted $query) use ($user, $token, $operation, $primary, $primaryBroker, &$attempted, &$blocked): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select')
                || ! str_contains($query->sql, '"password_reset_tokens"')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('reset_competitor');
            Password::swap(new PasswordBrokerManager($this->app));
            try {
                if ($operation === 'issuance') {
                    app(RequestPasswordReset::class)->handle((string) $user->email);
                } elseif ($operation === 'removal') {
                    app(RemovePassword::class)->handle((int) $user->id, null);
                } else {
                    app(ResetPassword::class)->handle((string) $user->email, 'Competing-Password-123!', 'Competing-Password-123!', $token);
                }
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? null) !== '55P03') {
                    throw $exception;
                }
                $blocked = str_contains($exception->getSql(), '"users"');
            } finally {
                DB::setDefaultConnection($primary);
                Password::swap($primaryBroker);
            }
        });

        try {
            self::assertSame(Password::PASSWORD_RESET, $this->reset($user, $token));
            self::assertTrue($attempted);
            self::assertTrue($blocked, 'A real competing writer must wait for the account throughout token validation and consumption.');
            self::assertTrue(Hash::check('Primary-Reset-Password-123!', (string) $user->refresh()->password));
            self::assertSame(1, DB::table('audit_events')->where('event', 'auth.password.reset')->count());

            if ($operation === 'removal') {
                app(RemovePassword::class)->handle((int) $user->id, null);
            }
            self::assertSame($operation === 'removal' ? Password::INVALID_USER : Password::INVALID_TOKEN, $this->reset($user, $token));
        } finally {
            DB::setDefaultConnection($primary);
            Password::swap($primaryBroker);
            DB::purge('reset_competitor');
        }
    }

    /** @return iterable<string,array{string}> */
    public static function rejectedResets(): iterable
    {
        foreach (['expired', 'unknown', 'password removed', 'anonymized', 'email changed'] as $reason) {
            yield $reason => [$reason];
        }
    }

    #[DataProvider('rejectedResets')]
    public function test_invalid_token_or_changed_current_account_state_cannot_report_success(string $reason): void
    {
        Event::fake([PasswordResetEvent::class]);
        $user = $this->account();
        $email = (string) $user->email;
        $token = Password::broker()->createToken($user);
        if ($reason === 'expired') {
            $this->travel((int) config('auth.passwords.users.expire', 60) + 1)->minutes();
        } elseif ($reason === 'unknown') {
            $token = 'not-the-issued-token';
        } elseif ($reason === 'password removed') {
            app(RemovePassword::class)->handle((int) $user->id, null);
        } elseif ($reason === 'anonymized') {
            app(AnonymizeAccount::class)->handle((int) $user->id, 'reset-finalization');
        } else {
            $user->forceFill(['email' => 'new-reset-address@example.test'])->save();
        }
        $before = $this->state($user);

        $status = app(ResetPassword::class)->handle($email, 'Rejected-Password-123!', 'Rejected-Password-123!', $token);

        self::assertSame(in_array($reason, ['expired', 'unknown'], true) ? Password::INVALID_TOKEN : Password::INVALID_USER, $status);
        self::assertSame($before, $this->state($user));
        Event::assertNotDispatched(PasswordResetEvent::class);
    }

    private function account(): User
    {
        $user = User::factory()->google()->create(['password' => Hash::make('password')]);
        $user->createToken('Reset fixture API token');
        app(RecordAccountSession::class)->handle((int) $user->id, 'old-reset-browser', 'Chrome/');

        return $user;
    }

    private function reset(User $user, string $token): string
    {
        return app(ResetPassword::class)->handle((string) $user->email, 'Primary-Reset-Password-123!', 'Primary-Reset-Password-123!', $token);
    }

    /** @return array<string,mixed> */
    private function state(User $user): array
    {
        return [
            'account' => $user->refresh()->getRawOriginal(),
            'tokens' => $user->tokens()->orderBy('id')->get()->toArray(),
            'reset_tokens' => DB::table('password_reset_tokens')->orderBy('email')->get()->map(static fn (object $row): array => (array) $row)->all(),
            'sessions' => AccountSession::query()->where('user_id', $user->id)->orderBy('id')->get()->toArray(),
            'audit' => DB::table('audit_events')->count(),
            'messages' => DB::table('notification_messages')->count(),
            'deliveries' => DB::table('notification_deliveries')->count(),
        ];
    }
}
