<?php

declare(strict_types=1);

namespace Tests\Contexts\Accounts\Authentication\Integration\Concurrency;

use App\Contexts\Accounts\Authentication\Models\AccountPasskey;
use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\WebAuthnAssertionFixture;
use Tests\TestCase;
use Webauthn\PublicKeyCredentialRequestOptions;

final class PasskeyConfirmationAtomicityV3Test extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('passkeys.relying_party_id', 'accounts.example.test');
        config()->set('passkeys.allowed_origins', ['https://accounts.example.test']);
    }

    /** @return iterable<string,array{bool}> */
    public static function failures(): iterable
    {
        yield 'verification audit insert' => [false];
        yield 'outer owner rollback' => [true];
    }

    #[DataProvider('failures')]
    public function test_failed_assertion_transaction_restores_counter_metadata_and_prior_proof(bool $outerRollback): void
    {
        [$user, $passkey, $fixture, $options, $request] = $this->account();
        $before = $passkey->refresh()->getRawOriginal();
        $prior = ['accounts.recent_authentication_at' => now()->subHour()->timestamp,
            'accounts.recent_authentication_method' => 'google', 'accounts.recent_authentication_credential' => 'prior-reference'];
        $request->session()->put($prior);
        $failed = false;
        if (! $outerRollback) {
            DB::listen(static function (QueryExecuted $query) use (&$failed): void {
                if (! $failed && str_starts_with($query->sql, 'insert into "audit_events"') && in_array('auth.passkey.verified', $query->bindings, true)) {
                    $failed = true;
                    throw new RuntimeException('Injected passkey confirmation failure.');
                }
            });
        }

        try {
            DB::transaction(static function () use ($user, $fixture, $options, $request, $prior, $outerRollback): void {
                app(VerifyPasskey::class)($fixture->assertion($options, $user->getPasskeyUserHandle()), $options, $user);
                foreach ($prior as $key => $value) {
                    self::assertSame($value, $request->session()->get($key));
                }
                if ($outerRollback) {
                    throw new RuntimeException('Injected passkey confirmation failure.');
                }
            });
            self::fail('Exercise a real failure after the maintained counter update.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected passkey confirmation failure.', $exception->getMessage());
        }

        self::assertTrue($failed || $outerRollback);
        self::assertSame($before, $passkey->refresh()->getRawOriginal());
        foreach ($prior as $key => $value) {
            self::assertSame($value, $request->session()->get($key));
        }
        self::assertSame(0, DB::table('audit_events')->where('event', 'auth.passkey.verified')->count());
    }

    public function test_real_signed_assertion_updates_counter_and_audit_before_proof_is_published_after_commit(): void
    {
        $this->freezeSecond();
        [$user, $passkey, $fixture, $options, $request] = $this->account();
        DB::transaction(static function () use ($user, $fixture, $options, $request): void {
            $verified = app(VerifyPasskey::class)($fixture->assertion($options, $user->getPasskeyUserHandle()), $options, $user);
            self::assertSame(1, $verified->credential['counter']);
            self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
        });

        self::assertSame(1, $passkey->refresh()->credential['counter']);
        self::assertTrue($passkey->last_used_at->equalTo(now()));
        self::assertSame(now()->timestamp, $request->session()->get('accounts.recent_authentication_at'));
        self::assertSame('passkey', $request->session()->get('accounts.recent_authentication_method'));
        self::assertSame($passkey->public_id, $request->session()->get('accounts.recent_authentication_credential'));
        $this->assertDatabaseHas('audit_events', ['event' => 'auth.passkey.verified', 'actor_user_id' => $user->id]);
    }

    /** @return iterable<string,array{string}> */
    public static function invalidAssertions(): iterable
    {
        foreach (['origin', 'challenge', 'signature', 'verification', 'user handle', 'counter'] as $invalid) {
            yield $invalid => [$invalid];
        }
    }

    #[DataProvider('invalidAssertions')]
    public function test_owner_adapter_preserves_the_maintained_assertion_rejections(string $invalid): void
    {
        [$user, $passkey, $fixture, $options, $request] = $this->account();
        if ($invalid === 'counter') {
            app(VerifyPasskey::class)($fixture->assertion($options, $user->getPasskeyUserHandle()), $options, $user);
            $request->session()->flush();
        }
        $before = $passkey->refresh()->getRawOriginal();
        $auditBefore = DB::table('audit_events')->count();
        $assertion = $fixture->assertion($options,
            $invalid === 'user handle' ? 'another-user' : $user->getPasskeyUserHandle(),
            origin: $invalid === 'origin' ? 'https://foreign.example.test' : 'https://accounts.example.test',
            challenge: $invalid === 'challenge' ? 'another-challenge' : null,
            userVerified: $invalid !== 'verification', validSignature: $invalid !== 'signature');

        try {
            app(VerifyPasskey::class)($assertion, $options, $user);
            self::fail('The maintained validator must reject the invalid signed assertion.');
        } catch (InvalidPasskeyException $exception) {
            self::assertArrayHasKey('credential', $exception->errors());
            self::assertSame($before, $passkey->refresh()->getRawOriginal());
            self::assertSame($auditBefore, DB::table('audit_events')->count());
            self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
        }
    }

    /** @return iterable<string,array{string}> */
    public static function bindings(): iterable
    {
        foreach (['guest', 'another account', 'deleted before commit', 'no session'] as $binding) {
            yield $binding => [$binding];
        }
    }

    #[DataProvider('bindings')]
    public function test_delayed_proof_respects_the_current_request_and_credential(string $binding): void
    {
        [$user, $passkey, $fixture, $options, $request] = $this->account();
        if ($binding === 'no session') {
            $this->app->instance('request', Request::create('/internal-verification'));
        }
        $other = $binding === 'another account' ? User::factory()->create() : null;
        DB::transaction(static function () use ($binding, $user, $passkey, $fixture, $options, $request, $other): void {
            app(VerifyPasskey::class)($fixture->assertion($options, $user->getPasskeyUserHandle()), $options);
            if ($binding === 'deleted before commit') {
                app(DeletePasskey::class)($user, $passkey);
            } elseif ($binding !== 'no session') {
                $request->setUserResolver(static fn (): ?User => $other);
            }
        });

        self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
        self::assertSame(1, DB::table('audit_events')->where('event', 'auth.passkey.verified')->count());
    }

    public function test_finalization_between_routing_lookup_and_account_lock_rejects_the_verified_candidate(): void
    {
        [$user, $passkey, $fixture, $options, $request] = $this->account();
        $finalized = false;
        DB::listen(static function (QueryExecuted $query) use ($user, &$finalized): void {
            if (! $finalized && str_starts_with($query->sql, 'select * from "passkeys"') && ! str_contains($query->sql, 'for update')) {
                $finalized = true;
                app(AnonymizeAccount::class)->handle((int) $user->id, 'passkey-routing-finalization');
            }
        });

        try {
            app(VerifyPasskey::class)($fixture->assertion($options, $user->getPasskeyUserHandle()), $options, $user);
            self::fail('A pre-lock lookup is routing information, not current account authority.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('account', $exception->errors());
        }
        self::assertTrue($finalized);
        self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
        self::assertSame(0, DB::table('audit_events')->where('event', 'auth.passkey.verified')->count());
        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
    }

    public function test_real_competing_deletion_waits_for_account_lock_before_verifier_locks_the_passkey(): void
    {
        [$user, $passkey, $fixture, $options] = $this->account();
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.passkey_verification_competitor', array_replace(DB::connection()->getConfig(), ['name' => 'passkey_verification_competitor']));
        DB::connection('passkey_verification_competitor')->statement("SET lock_timeout = '100ms'");
        $attempted = false;
        $blocked = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $user, $passkey, &$attempted, &$blocked): void {
            if ($attempted || $query->connectionName !== $primary || ! str_contains($query->sql, 'for update')) {
                return;
            }
            self::assertStringContainsString('"users"', $query->sql, 'The first lock must belong to the account.');
            $attempted = true;
            DB::setDefaultConnection('passkey_verification_competitor');
            try {
                app(DeletePasskey::class)($user, $passkey);
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
            app(VerifyPasskey::class)($fixture->assertion($options, $user->getPasskeyUserHandle()), $options, $user);
            self::assertTrue($attempted && $blocked);
            self::assertSame(1, $passkey->refresh()->credential['counter']);
            app(DeletePasskey::class)($user, $passkey);
            $this->expectException(InvalidPasskeyException::class);
            app(VerifyPasskey::class)($fixture->assertion($options, $user->getPasskeyUserHandle(), counter: 2), $options, $user);
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('passkey_verification_competitor');
        }
    }

    /** @return array{User,AccountPasskey,WebAuthnAssertionFixture,PublicKeyCredentialRequestOptions,Request} */
    private function account(): array
    {
        $user = User::factory()->create();
        $fixture = WebAuthnAssertionFixture::create();
        $registration = app(GenerateRegistrationOptions::class)($user);
        $passkey = app(StorePasskey::class)($user, 'Assertion key', $fixture->registration($registration), $registration);
        self::assertInstanceOf(AccountPasskey::class, $passkey);
        $request = Request::create('/user/passkeys/confirm');
        $request->setLaravelSession(app('session.store'));
        $this->app->instance('request', $request);
        // The application's request-rebinding hook installs its guard resolver.
        // Bind the explicit authenticated fixture only after that hook has run.
        $request->setUserResolver(static fn (): User => $user);

        return [$user, $passkey, $fixture, app(GenerateVerificationOptions::class)($user), $request];
    }
}
