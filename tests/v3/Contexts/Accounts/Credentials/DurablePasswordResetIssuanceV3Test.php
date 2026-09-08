<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Credentials;

use App\Contexts\Accounts\Credentials\Actions\QueuePasswordResetDelivery;
use App\Contexts\Accounts\Credentials\Actions\RemovePassword;
use App\Contexts\Accounts\Credentials\Actions\RequestPasswordReset;
use App\Contexts\Accounts\Credentials\Actions\ResetPassword;
use App\Contexts\Accounts\Credentials\Actions\SendRequestedPasswordReset;
use App\Contexts\Accounts\Credentials\Notifications\ResetKingshotAlliancePassword;
use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Profile\Actions\ChangePassword;
use App\Contexts\Accounts\Profile\Actions\PromotePendingAccountEmail;
use App\Shared\Infrastructure\Messaging\Outbox\Actions\PublishOutboxBatch;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\v3\TestCase;

final class DurablePasswordResetIssuanceV3Test extends TestCase
{
    use DatabaseMigrations;

    public function test_real_request_queues_protected_current_token_and_delivery_scrubs_the_secret_but_preserves_redemption(): void
    {
        Notification::fake();
        Event::fake([PasswordResetLinkSent::class]);
        $user = User::factory()->create(['email' => 'reset@example.test']);
        $this->post('/forgot-password', ['email' => ' RESET@example.test '])->assertSessionHas('status');
        $record = $this->tokenRecord($user);
        $token = $this->plainToken($user);
        self::assertNotSame($token, $record['token']);
        self::assertTrue(Password::tokenExists($user, $token));
        $intent = OutboxMessage::query()->where('event_type', QueuePasswordResetDelivery::EVENT_TYPE)->sole();
        self::assertSame(['delivery_id' => $record['delivery_id']], $intent->payload);
        self::assertStringNotContainsString($token, json_encode($intent->getRawOriginal(), JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString((string) $user->email, json_encode($intent->payload, JSON_THROW_ON_ERROR));
        Notification::assertNothingSent();
        Event::assertNotDispatched(PasswordResetLinkSent::class);

        // Both the maintained throttle and a repeated notification hook preserve one occurrence.
        app(RequestPasswordReset::class)->handle((string) $user->email);
        $user->sendPasswordResetNotification($token);
        self::assertSame($record, $this->tokenRecord($user));
        self::assertSame(1, OutboxMessage::query()->where('event_type', QueuePasswordResetDelivery::EVENT_TYPE)->count());
        self::assertSame(1, app(PublishOutboxBatch::class)->handle(100));
        Notification::assertSentTo($user, ResetKingshotAlliancePassword::class, static fn (ResetKingshotAlliancePassword $notification): bool => $notification->token === $token);
        self::assertNull($this->tokenRecord($user)['encrypted_delivery_token']);
        self::assertTrue(Password::tokenExists($user, $token));
        Event::assertDispatchedTimes(PasswordResetLinkSent::class, 1);

        self::assertSame(Password::PASSWORD_RESET, $this->reset($user, $token));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        self::assertTrue(Hash::check('ReplacementPassword123!', (string) $user->refresh()->password));
    }

    public function test_transport_retry_runs_outside_transactions_and_never_retains_a_secret_bearing_failure_message(): void
    {
        $this->freezeSecond();
        Event::fake([PasswordResetLinkSent::class]);
        $user = User::factory()->create();
        app(RequestPasswordReset::class)->handle((string) $user->email);
        $token = $this->plainToken($user);
        $observed = [];
        Notification::shouldReceive('send')->twice()->andReturnUsing(static function (User $recipient, ResetKingshotAlliancePassword $notification) use (&$observed): void {
            $observed[] = [DB::transactionLevel(), (int) $recipient->id, $notification->token];
            if (count($observed) === 1) {
                throw new RuntimeException('Synthetic transport echoed reset token '.$notification->token);
            }
        });
        $intent = OutboxMessage::query()->where('event_type', QueuePasswordResetDelivery::EVENT_TYPE)->sole();
        self::assertSame(0, app(PublishOutboxBatch::class)->handle(1));
        self::assertSame('Password reset email delivery failed.', $intent->refresh()->last_error);
        self::assertNotNull($this->tokenRecord($user)['encrypted_delivery_token']);
        Event::assertNotDispatched(PasswordResetLinkSent::class);
        $this->travel(60)->seconds();
        self::assertSame(1, app(PublishOutboxBatch::class)->handle(1));

        self::assertSame([[0, (int) $user->id, $token], [0, (int) $user->id, $token]], $observed);
        self::assertNull($intent->refresh()->last_error);
        self::assertNull($this->tokenRecord($user)['encrypted_delivery_token']);
        Event::assertDispatchedTimes(PasswordResetLinkSent::class, 1);
    }

    /** @return iterable<string,array{bool}> */
    public static function tokenIssuances(): iterable
    {
        yield 'first issuance' => [false];
        yield 'replacement issuance' => [true];
    }

    #[DataProvider('tokenIssuances')]
    public function test_delivery_intent_failure_rolls_back_token_creation_and_protected_delivery_state(bool $replace): void
    {
        Notification::fake();
        $user = User::factory()->create();
        if ($replace) {
            app(RequestPasswordReset::class)->handle((string) $user->email);
            $this->travel(61)->seconds();
        }
        $before = DB::table('password_reset_tokens')->get()->map(static fn (object $row): array => (array) $row)->all();
        $intentCount = OutboxMessage::query()->count();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"') && in_array(QueuePasswordResetDelivery::EVENT_TYPE, $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected reset delivery intent failure.');
            }
        });

        try {
            app(RequestPasswordReset::class)->handle((string) $user->email);
            self::fail('Token creation must commit with recoverable delivery.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected reset delivery intent failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, DB::table('password_reset_tokens')->get()->map(static fn (object $row): array => (array) $row)->all());
        self::assertSame($intentCount, OutboxMessage::query()->count());
        Notification::assertNothingSent();
    }

    /** @return iterable<string,array{string}> */
    public static function ineligibleAccounts(): iterable
    {
        foreach (['missing', 'password absent', 'finalized'] as $state) {
            yield $state => [$state];
        }
    }

    #[DataProvider('ineligibleAccounts')]
    public function test_generic_http_response_does_not_issue_tokens_for_ineligible_accounts(string $state): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $email = (string) $user->email;
        if ($state === 'missing') {
            $email = 'missing@example.test';
        } elseif ($state === 'password absent') {
            $user->forceFill(['password' => null])->save();
        } else {
            app(AnonymizeAccount::class)->handle((int) $user->id, 'reset-issuance-finalization');
        }

        $this->from('/forgot-password')->post('/forgot-password', ['email' => $email])
            ->assertRedirect('/forgot-password')->assertSessionHas('status', 'If an account exists for that email address, a password reset link has been sent.');
        self::assertSame(0, DB::table('password_reset_tokens')->count());
        self::assertSame(0, OutboxMessage::query()->where('event_type', QueuePasswordResetDelivery::EVENT_TYPE)->count());
        Notification::assertNothingSent();
    }

    /** @return iterable<string,array{string}> */
    public static function staleDeliveries(): iterable
    {
        foreach (['password removed', 'password changed', 'reset consumed', 'email promoted', 'finalized', 'expired', 'superseded'] as $state) {
            yield $state => [$state];
        }
    }

    #[DataProvider('staleDeliveries')]
    public function test_delayed_delivery_rechecks_current_token_and_credential_lifecycle(string $state): void
    {
        Notification::fake();
        $user = User::factory()->google()->create(['password' => Hash::make('password'), 'pending_email' => 'promoted@example.test']);
        $email = (string) $user->email;
        app(RequestPasswordReset::class)->handle($email);
        $token = $this->plainToken($user);
        if ($state === 'password removed') {
            app(RemovePassword::class)->handle((int) $user->id, null);
        } elseif ($state === 'password changed') {
            app(ChangePassword::class)->handle((int) $user->id, 'password', 'ChangedPassword123!', null);
        } elseif ($state === 'reset consumed') {
            self::assertSame(Password::PASSWORD_RESET, $this->reset($user, $token));
        } elseif ($state === 'email promoted') {
            app(PromotePendingAccountEmail::class)->handle((int) $user->id, sha1('promoted@example.test'));
        } elseif ($state === 'finalized') {
            app(AnonymizeAccount::class)->handle((int) $user->id, 'delayed-reset-finalization');
        } elseif ($state === 'expired') {
            $this->travel(61)->minutes();
        } else {
            $this->travel(61)->seconds();
            app(RequestPasswordReset::class)->handle($email);
        }

        app(PublishOutboxBatch::class)->handle(100);

        Notification::assertSentToTimes($user, ResetKingshotAlliancePassword::class, $state === 'superseded' ? 1 : 0);
        Notification::assertNotSentTo($user, ResetKingshotAlliancePassword::class, static fn (ResetKingshotAlliancePassword $notification): bool => $notification->token === $token);
        if (in_array($state, ['expired', 'superseded'], true)) {
            self::assertNull($this->tokenRecord($user)['encrypted_delivery_token']);
        } else {
            $this->assertDatabaseMissing('password_reset_tokens', ['email' => $email]);
        }
    }

    public function test_scheduled_maintained_expiry_cleanup_removes_pending_secrets_even_when_outbox_retries_are_exhausted(): void
    {
        $expired = User::factory()->create();
        app(RequestPasswordReset::class)->handle((string) $expired->email);
        OutboxMessage::query()->where('event_type', QueuePasswordResetDelivery::EVENT_TYPE)->update(['attempts' => 100]);
        $this->travel(61)->minutes();
        $current = User::factory()->create();
        app(RequestPasswordReset::class)->handle((string) $current->email);

        self::assertSame(0, Artisan::call('auth:clear-resets'));

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $expired->email]);
        self::assertNotNull($this->tokenRecord($current)['encrypted_delivery_token']);
        self::assertSame(2, OutboxMessage::query()->where('event_type', QueuePasswordResetDelivery::EVENT_TYPE)->count());
        foreach (OutboxMessage::query()->where('event_type', QueuePasswordResetDelivery::EVENT_TYPE)->get() as $intent) {
            self::assertArrayNotHasKey('token', $intent->payload);
            self::assertArrayNotHasKey('encrypted_delivery_token', $intent->payload);
        }
    }

    public function test_delivery_id_cannot_read_or_scrub_another_accounts_pending_recovery_secret(): void
    {
        Notification::fake();
        $first = User::factory()->create();
        $second = User::factory()->create();
        app(RequestPasswordReset::class)->handle((string) $first->email);
        app(RequestPasswordReset::class)->handle((string) $second->email);
        $firstBefore = $this->tokenRecord($first);
        $secondBefore = $this->tokenRecord($second);
        $intent = OutboxMessage::query()->where('event_type', QueuePasswordResetDelivery::EVENT_TYPE)
            ->where('aggregate_id', (string) $first->id)->sole();

        app(SendRequestedPasswordReset::class)->handle(new OutboxPublished(
            messageId: $intent->id,
            allianceId: null,
            eventType: $intent->event_type,
            aggregateType: User::class,
            aggregateId: (string) $second->id,
            idempotencyKey: $intent->idempotency_key,
            payload: $intent->payload,
            occurredAt: $intent->occurred_at->toIso8601String(),
        ));

        self::assertSame($firstBefore, $this->tokenRecord($first));
        self::assertSame($secondBefore, $this->tokenRecord($second));
        Notification::assertNothingSent();
        self::assertSame(2, app(PublishOutboxBatch::class)->handle(100));
        Notification::assertSentToTimes($first, ResetKingshotAlliancePassword::class, 1);
        Notification::assertSentToTimes($second, ResetKingshotAlliancePassword::class, 1);
    }

    /** @return iterable<string,array{string}> */
    public static function competingCredentialWrites(): iterable
    {
        foreach (['reset', 'removal', 'finalization'] as $operation) {
            yield $operation => [$operation];
        }
    }

    #[DataProvider('competingCredentialWrites')]
    public function test_issuance_holds_the_account_through_maintained_token_replacement_and_delivery_intent(string $operation): void
    {
        $user = User::factory()->google()->create(['password' => Hash::make('password')]);
        $oldToken = Password::createToken($user);
        $this->travel(61)->seconds();
        $primary = DB::getDefaultConnection();
        $primaryBroker = Password::getFacadeRoot();
        config()->set('database.connections.issuance_competitor', array_replace(DB::connection()->getConfig(), ['name' => 'issuance_competitor']));
        DB::connection('issuance_competitor')->statement("SET lock_timeout = '100ms'");
        $attempted = false;
        $blocked = false;
        DB::listen(function (QueryExecuted $query) use ($user, $oldToken, $operation, $primary, $primaryBroker, &$attempted, &$blocked): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select') || ! str_contains($query->sql, '"password_reset_tokens"')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('issuance_competitor');
            Password::swap(new PasswordBrokerManager($this->app));
            try {
                match ($operation) {
                    'reset' => $this->reset($user, $oldToken),
                    'removal' => app(RemovePassword::class)->handle((int) $user->id, null),
                    'finalization' => app(AnonymizeAccount::class)->handle((int) $user->id, 'competing-issuance-finalization'),
                };
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
            app(RequestPasswordReset::class)->handle((string) $user->email);
            self::assertTrue($attempted);
            self::assertTrue($blocked);
            self::assertFalse(Password::tokenExists($user, $oldToken));
            self::assertNotNull($this->tokenRecord($user)['encrypted_delivery_token']);
            self::assertSame(1, OutboxMessage::query()->where('event_type', QueuePasswordResetDelivery::EVENT_TYPE)->count());
        } finally {
            DB::setDefaultConnection($primary);
            Password::swap($primaryBroker);
            DB::purge('issuance_competitor');
        }
    }

    private function plainToken(User $user): string
    {
        $encrypted = $this->tokenRecord($user)['encrypted_delivery_token'];
        self::assertIsString($encrypted);

        return Crypt::decryptString($encrypted);
    }

    private function reset(User $user, string $token): string
    {
        return app(ResetPassword::class)->handle((string) $user->email, 'ReplacementPassword123!', 'ReplacementPassword123!', $token);
    }

    /** @return array{email:string,token:string,delivery_id:?string,encrypted_delivery_token:?string,created_at:?string} */
    private function tokenRecord(User $user): array
    {
        $record = DB::table('password_reset_tokens')->where('email', (string) $user->email)->first();
        self::assertNotNull($record);

        return (array) $record;
    }
}
