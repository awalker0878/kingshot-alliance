<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Identity;

use App\Contexts\Accounts\Authentication\Actions\ConnectGoogleAccount;
use App\Contexts\Accounts\Authentication\Actions\RecordAccountSession;
use App\Contexts\Accounts\Authentication\Actions\RenameAccountPasskey;
use App\Contexts\Accounts\Authentication\Actions\RevokeAccountSession;
use App\Contexts\Accounts\Authentication\Actions\RevokeOtherAccountSessions;
use App\Contexts\Accounts\Authentication\Models\AccountPasskey;
use App\Contexts\Accounts\Credentials\Actions\AddPassword;
use App\Contexts\Accounts\Credentials\Actions\RemovePassword;
use App\Contexts\Accounts\EmailVerification\Actions\RequestEmailVerification;
use App\Contexts\Accounts\EmailVerification\Enums\EmailVerificationTarget;
use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Actions\RecordAccountDeletionLifecycle;
use App\Contexts\Accounts\Identity\Actions\RecordAccountIdentityUse;
use App\Contexts\Accounts\Identity\Actions\RemoveAccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TwoFactorManager;
use App\Contexts\Accounts\Profile\Actions\ChangePassword;
use App\Contexts\Accounts\Profile\Actions\PromotePendingAccountEmail;
use App\Contexts\Accounts\Profile\Actions\RequestAccountEmailChange;
use App\Contexts\Accounts\Profile\Actions\UpdateProfile;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\v3\Support\WebAuthnRegistrationFixture;
use Tests\v3\TestCase;

final class FinalizedAccountMutationV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{string}> */
    public static function ordinaryMutations(): iterable
    {
        foreach (['add password', 'remove password', 'change password', 'profile', 'request email', 'promote email',
            'connect Google', 'disconnect Google', 'store passkey', 'delete passkey', 'rename passkey',
            'revoke session', 'revoke others', 'begin MFA', 'confirm MFA', 'regenerate recovery', 'disable MFA',
            'consume recovery', 'request deletion', 'cancel deletion'] as $operation) {
            yield $operation => [$operation];
        }
    }

    #[DataProvider('ordinaryMutations')]
    public function test_stale_commands_cannot_change_the_finalized_account_or_create_side_effects(string $operation): void
    {
        config()->set('passkeys.relying_party_id', 'accounts.example.test');
        config()->set('passkeys.allowed_origins', ['https://accounts.example.test']);
        $staleUser = User::factory()->create(['pending_email' => 'pending@example.test']);
        $options = app(GenerateRegistrationOptions::class)($staleUser);
        $credential = WebAuthnRegistrationFixture::credential($options);
        $stalePasskey = new AccountPasskey(['user_id' => $staleUser->id, 'name' => 'Old key']);
        app(AnonymizeAccount::class)->handle((int) $staleUser->id, 'finalized-command');
        $before = $this->state((int) $staleUser->id);

        try {
            match ($operation) {
                'add password' => app(AddPassword::class)->handle((int) $staleUser->id, 'NewPassword123!', null),
                'remove password' => app(RemovePassword::class)->handle((int) $staleUser->id, null),
                'change password' => app(ChangePassword::class)->handle((int) $staleUser->id, 'password', 'NewPassword123!', null),
                'profile' => app(UpdateProfile::class)->handle((int) $staleUser->id, 'Restored name', 'Europe/London'),
                'request email' => app(RequestAccountEmailChange::class)->handle((int) $staleUser->id, 'restored@example.test'),
                'promote email' => app(PromotePendingAccountEmail::class)->handle((int) $staleUser->id, sha1('pending@example.test')),
                'connect Google' => app(ConnectGoogleAccount::class)->handle((int) $staleUser->id, 'restored-subject', 'restored@example.test'),
                'disconnect Google' => app(RemoveAccountIdentity::class)->handle((int) $staleUser->id, 'google', null),
                'store passkey' => app(StorePasskey::class)($staleUser, 'Restored key', $credential, $options),
                'delete passkey' => app(DeletePasskey::class)($staleUser, $stalePasskey),
                'rename passkey' => app(RenameAccountPasskey::class)->handle((int) $staleUser->id, 'old-key', 'Restored name'),
                'revoke session' => app(RevokeAccountSession::class)->handle((int) $staleUser->id, 'old-session', 'current-session'),
                'revoke others' => app(RevokeOtherAccountSessions::class)->handle((int) $staleUser->id, null),
                'begin MFA' => app(TwoFactorManager::class)->begin($staleUser),
                'confirm MFA' => app(TwoFactorManager::class)->confirm($staleUser, '123456'),
                'regenerate recovery' => app(TwoFactorManager::class)->regenerateRecoveryCodes($staleUser),
                'disable MFA' => app(TwoFactorManager::class)->disable($staleUser),
                'consume recovery' => app(TwoFactorManager::class)->consumeRecoveryCode($staleUser, 'old-recovery-code'),
                'request deletion' => app(RecordAccountDeletionLifecycle::class)->requested((int) $staleUser->id, 'late-request'),
                'cancel deletion' => app(RecordAccountDeletionLifecycle::class)->cancelled((int) $staleUser->id, 'late-cancel'),
            };
            self::fail('A command prepared before finalization must recheck the current account.');
        } catch (ValidationException $exception) {
            self::assertSame(['account' => ['This account has already been deleted.']], $exception->errors());
        }

        self::assertSame($before, $this->state((int) $staleUser->id));
        self::assertNull($staleUser->anonymized_at, 'The caller deliberately retains its pre-finalization snapshot.');
    }

    public function test_finalized_or_missing_accounts_cannot_register_sessions_and_finalized_verification_is_a_noop(): void
    {
        $user = User::factory()->unverified()->create();
        app(AnonymizeAccount::class)->handle((int) $user->id, 'finalized-tracking');
        $before = $this->state((int) $user->id);

        self::assertFalse(app(RecordAccountSession::class)->handle((int) $user->id, 'late-session', 'Firefox/'));
        self::assertFalse(app(RecordAccountSession::class)->handle((int) $user->id + 1000, 'missing-account', 'Firefox/'));
        app(RequestEmailVerification::class)->handle((int) $user->id, EmailVerificationTarget::Account);
        app(RequestEmailVerification::class)->handle((int) $user->id, EmailVerificationTarget::Pending);

        self::assertSame($before, $this->state((int) $user->id));
    }

    /** @return iterable<string,array{bool}> */
    public static function competingOperations(): iterable
    {
        yield 'profile command' => [false];
        yield 'session registration' => [true];
    }

    #[DataProvider('competingOperations')]
    public function test_finalization_after_request_start_but_before_owner_lock_prevents_the_pending_write(bool $track): void
    {
        $user = User::factory()->create();
        $primary = $this->competingConnection();
        $finalized = false;
        DB::connection()->beforeExecuting(function (string $sql) use ($user, $primary, &$finalized): void {
            if ($finalized || ! str_starts_with($sql, 'select') || ! str_contains($sql, '"users"') || ! str_contains($sql, 'for update')) {
                return;
            }
            $finalized = true;
            DB::setDefaultConnection('finalization_competitor');
            try {
                app(AnonymizeAccount::class)->handle((int) $user->id, 'finalization-wins');
            } finally {
                DB::setDefaultConnection($primary);
            }
        });

        try {
            if ($track) {
                self::assertFalse(app(RecordAccountSession::class)->handle((int) $user->id, 'late-session', 'Firefox/'));
            } else {
                try {
                    app(UpdateProfile::class)->handle((int) $user->id, 'Restored name', 'Europe/London');
                    self::fail('The command must recheck lifecycle after obtaining the lock.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey('account', $exception->errors());
                }
            }
            self::assertTrue($finalized);
            self::assertNotNull($user->refresh()->anonymized_at);
            self::assertSame('Deleted User', $user->name);
            self::assertSame(0, DB::table('account_sessions')->count());
            self::assertSame(['account.anonymized'], DB::table('audit_events')->pluck('event')->all());
        } finally {
            DB::purge('finalization_competitor');
        }
    }

    #[DataProvider('competingOperations')]
    public function test_owner_lock_blocks_finalization_until_the_current_mutation_is_committed(bool $track): void
    {
        $user = User::factory()->create();
        $primary = $this->competingConnection();
        $attempted = false;
        $blocked = false;
        DB::listen(static function (QueryExecuted $query) use ($user, $primary, &$attempted, &$blocked): void {
            if ($attempted || $query->connectionName !== $primary || ! str_contains($query->sql, '"users"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('finalization_competitor');
            try {
                app(AnonymizeAccount::class)->handle((int) $user->id, 'blocked-finalization');
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? null) !== '55P03') {
                    throw $exception;
                }
                $blocked = str_contains($exception->getSql(), '"users"');
            } finally {
                DB::setDefaultConnection($primary);
            }
        });

        try {
            if ($track) {
                self::assertTrue(app(RecordAccountSession::class)->handle((int) $user->id, 'current-session', 'Firefox/'));
            } else {
                app(UpdateProfile::class)->handle((int) $user->id, 'Updated name', 'Europe/London');
            }
            self::assertTrue($attempted);
            self::assertTrue($blocked);
            app(AnonymizeAccount::class)->handle((int) $user->id, 'finalize-after-command');
            self::assertNotNull($user->refresh()->anonymized_at);
            self::assertSame('Deleted User', $user->name);
            self::assertSame(0, DB::table('account_sessions')->count());
        } finally {
            DB::purge('finalization_competitor');
        }
    }

    public function test_provider_use_rechecks_lifecycle_after_discovering_its_account(): void
    {
        $user = User::factory()->create();
        $identityId = app(ConnectGoogleAccount::class)->handle((int) $user->id, 'before-finalization', 'before@example.test');
        $primary = $this->competingConnection();
        $finalized = false;
        DB::listen(static function (QueryExecuted $query) use ($user, $primary, &$finalized): void {
            if ($finalized || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select') || ! str_contains($query->sql, '"account_identities"')) {
                return;
            }
            $finalized = true;
            DB::setDefaultConnection('finalization_competitor');
            try {
                app(AnonymizeAccount::class)->handle((int) $user->id, 'provider-finalization');
            } finally {
                DB::setDefaultConnection($primary);
            }
        });

        try {
            try {
                app(RecordAccountIdentityUse::class)->handle($identityId, 'late@example.test', true);
                self::fail('Provider use must not write from its initial identity snapshot.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('account', $exception->errors());
            }
            self::assertTrue($finalized);
            self::assertSame(0, DB::table('account_identities')->count());
            self::assertSame(0, DB::table('audit_events')->where('event', 'auth.google.identity_used')->count());
        } finally {
            DB::purge('finalization_competitor');
        }
    }

    private function competingConnection(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.finalization_competitor', array_replace(DB::connection()->getConfig(), ['name' => 'finalization_competitor']));
        DB::connection('finalization_competitor')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,mixed> */
    private function state(int $userId): array
    {
        $state = ['user' => User::query()->findOrFail($userId)->getRawOriginal()];
        foreach (['account_identities', 'account_sessions', 'passkeys', 'password_reset_tokens', 'personal_access_tokens',
            'audit_events', 'outbox_messages', 'notification_messages', 'notification_deliveries'] as $table) {
            $state[$table] = DB::table($table)->count();
        }

        return $state;
    }
}
