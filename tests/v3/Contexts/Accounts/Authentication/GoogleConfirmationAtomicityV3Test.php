<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Authentication\Actions\ConfirmGoogleAccount;
use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Actions\RemoveAccountIdentity;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\v3\TestCase;

final class GoogleConfirmationAtomicityV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{bool}> */
    public static function failures(): iterable
    {
        yield 'confirmation audit insert' => [false];
        yield 'outer owner rollback' => [true];
    }

    #[DataProvider('failures')]
    public function test_failed_confirmation_rolls_back_provider_metadata_audits_and_new_proof(bool $outerRollback): void
    {
        [$user, $identity, $request] = $this->account();
        $before = $identity->getRawOriginal();
        $failed = false;
        if (! $outerRollback) {
            DB::listen(static function (QueryExecuted $query) use (&$failed): void {
                if (! $failed && str_starts_with($query->sql, 'insert into "audit_events"') && in_array('auth.reauthenticated', $query->bindings, true)) {
                    $failed = true;
                    throw new RuntimeException('Injected Google confirmation failure.');
                }
            });
        }

        try {
            DB::transaction(static function () use ($request, $user, $identity, $outerRollback): void {
                app(ConfirmGoogleAccount::class)->handle($request, (int) $user->id, $identity->provider_subject, 'updated@example.test');
                self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
                if ($outerRollback) {
                    throw new RuntimeException('Injected Google confirmation failure.');
                }
            });
            self::fail('The owner must persist confirmation audit before granting proof.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected Google confirmation failure.', $exception->getMessage());
        }

        self::assertTrue($outerRollback || $failed);
        self::assertSame($before, $identity->refresh()->getRawOriginal());
        self::assertSame(0, DB::table('audit_events')->count());
        self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
    }

    public function test_successful_confirmation_records_current_identity_use_then_publishes_bound_proof_after_commit(): void
    {
        $this->freezeSecond();
        [$user, $identity, $request] = $this->account();
        DB::transaction(static function () use ($request, $user, $identity): void {
            app(ConfirmGoogleAccount::class)->handle($request, (int) $user->id, $identity->provider_subject, 'updated@example.test');
            self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
        });

        self::assertSame('updated@example.test', $identity->refresh()->provider_email);
        self::assertSame(now()->timestamp, $request->session()->get('accounts.recent_authentication_at'));
        self::assertSame('google', $request->session()->get('accounts.recent_authentication_method'));
        self::assertSame((string) $identity->id, $request->session()->get('accounts.recent_authentication_credential'));
        self::assertSame(['auth.google.identity_used', 'auth.reauthenticated'], DB::table('audit_events')->orderBy('event')->pluck('event')->all());
    }

    /** @return iterable<string,array{string}> */
    public static function rejectedConfirmations(): iterable
    {
        foreach (['wrong account', 'wrong subject', 'disconnected', 'finalized'] as $state) {
            yield $state => [$state];
        }
    }

    #[DataProvider('rejectedConfirmations')]
    public function test_current_account_binding_and_identity_rejections_never_grant_proof(string $state): void
    {
        [$user, $identity, $request] = $this->account();
        $expectedId = (int) $user->id;
        $subject = $identity->provider_subject;
        if ($state === 'wrong account') {
            $expectedId = (int) User::factory()->create()->id;
        } elseif ($state === 'wrong subject') {
            $subject = 'another-provider-subject';
        } elseif ($state === 'disconnected') {
            app(RemoveAccountIdentity::class)->handle((int) $user->id, 'google', null);
        } else {
            app(AnonymizeAccount::class)->handle((int) $user->id, 'confirmation-finalization');
        }
        $auditBefore = DB::table('audit_events')->count();

        try {
            app(ConfirmGoogleAccount::class)->handle($request, $expectedId, $subject, 'rejected@example.test');
            self::fail('Confirmation must use the current account and exact provider subject.');
        } catch (HttpException $exception) {
            self::assertNotSame('finalized', $state);
            self::assertSame(403, $exception->getStatusCode());
        } catch (ValidationException $exception) {
            self::assertSame('finalized', $state);
            self::assertArrayHasKey('account', $exception->errors());
        }

        self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
        self::assertSame($auditBefore + ($state === 'wrong subject' ? 1 : 0), DB::table('audit_events')->count());
        self::assertSame(0, DB::table('audit_events')->where('event', 'auth.reauthenticated')->count());
        if ($state === 'wrong subject') {
            $this->assertDatabaseHas('audit_events', ['event' => 'auth.google.identity_failed', 'actor_user_id' => $user->id]);
        }
    }

    public function test_later_identity_removal_in_the_outer_transaction_suppresses_obsolete_proof(): void
    {
        [$user, $identity, $request] = $this->account();
        DB::transaction(static function () use ($request, $user, $identity): void {
            app(ConfirmGoogleAccount::class)->handle($request, (int) $user->id, $identity->provider_subject, 'updated@example.test');
            app(RemoveAccountIdentity::class)->handle((int) $user->id, 'google', null);
        });

        self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
        $this->assertDatabaseMissing('account_identities', ['id' => $identity->id]);
    }

    /** @return array{User,AccountIdentity,Request} */
    private function account(): array
    {
        $user = User::factory()->google()->create(['password' => Hash::make('password')]);
        $identity = AccountIdentity::query()->where('user_id', $user->id)->where('provider', 'google')->sole();
        $request = Request::create('/auth/google/callback');
        $request->setUserResolver(static fn (): User => $user);
        $request->setLaravelSession(app('session.store'));

        return [$user, $identity, $request];
    }
}
