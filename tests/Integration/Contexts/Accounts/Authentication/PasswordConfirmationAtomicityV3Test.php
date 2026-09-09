<?php

declare(strict_types=1);

namespace Tests\Integration\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Authentication\Actions\ConfirmAccountPassword;
use App\Contexts\Accounts\Credentials\Actions\RemovePassword;
use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Profile\Actions\ChangePassword;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class PasswordConfirmationAtomicityV3Test extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{bool}> */
    public static function failures(): iterable
    {
        yield 'audit insert' => [false];
        yield 'outer owner rollback' => [true];
    }

    #[DataProvider('failures')]
    public function test_failed_confirmation_preserves_prior_proof_and_rolls_back_its_audit(bool $outerRollback): void
    {
        $user = User::factory()->create();
        $request = $this->requestFor($user);
        $prior = [
            'accounts.recent_authentication_at' => now()->subHour()->timestamp,
            'accounts.recent_authentication_method' => 'google',
            'accounts.recent_authentication_credential' => 'prior-credential',
        ];
        $request->session()->put($prior);
        $failed = false;
        if (! $outerRollback) {
            DB::listen(static function (QueryExecuted $query) use (&$failed): void {
                if (! $failed && str_starts_with($query->sql, 'insert into "audit_events"') && in_array('auth.password.confirmed', $query->bindings, true)) {
                    $failed = true;
                    throw new RuntimeException('Injected confirmation failure.');
                }
            });
        }

        try {
            DB::transaction(static function () use ($request, $prior, $outerRollback): void {
                app(ConfirmAccountPassword::class)->handle($request, 'password');
                foreach ($prior as $key => $value) {
                    self::assertSame($value, $request->session()->get($key));
                }
                if ($outerRollback) {
                    throw new RuntimeException('Injected confirmation failure.');
                }
            });
            self::fail('Exercise a real failure before the containing commit.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected confirmation failure.', $exception->getMessage());
        }

        self::assertTrue($outerRollback || $failed);
        foreach ($prior as $key => $value) {
            self::assertSame($value, $request->session()->get($key));
        }
        self::assertSame(0, DB::table('audit_events')->where('event', 'auth.password.confirmed')->count());
    }

    public function test_successful_confirmation_publishes_canonical_proof_after_the_outermost_commit(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $request = $this->requestFor($user);
        DB::transaction(static function () use ($request): void {
            app(ConfirmAccountPassword::class)->handle($request, 'password');
            self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
            self::assertSame(1, DB::table('audit_events')->where('event', 'auth.password.confirmed')->count());
        });

        self::assertSame(now()->timestamp, $request->session()->get('accounts.recent_authentication_at'));
        self::assertSame('password', $request->session()->get('accounts.recent_authentication_method'));
        self::assertNull($request->session()->get('accounts.recent_authentication_credential'));
    }

    /** @return iterable<string,array{string}> */
    public static function changedCredentials(): iterable
    {
        foreach (['changed', 'removed', 'finalized'] as $state) {
            yield $state => [$state];
        }
    }

    #[DataProvider('changedCredentials')]
    public function test_a_request_snapshot_cannot_confirm_a_changed_or_finalized_password(string $state): void
    {
        $user = User::factory()->google()->create(['password' => Hash::make('password')]);
        $request = $this->requestFor($user);
        if ($state === 'changed') {
            app(ChangePassword::class)->handle((int) $user->id, 'password', 'ChangedPassword123!', null);
        } elseif ($state === 'removed') {
            app(RemovePassword::class)->handle((int) $user->id, null);
        } else {
            app(AnonymizeAccount::class)->handle((int) $user->id, 'confirmation-finalization');
        }
        $auditBefore = DB::table('audit_events')->count();
        self::assertTrue(Hash::check('password', (string) $user->password), 'The request intentionally retains its original authenticated snapshot.');

        try {
            app(ConfirmAccountPassword::class)->handle($request, 'password');
            self::fail('Confirmation must validate the currently locked account.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey($state === 'finalized' ? 'account' : 'password', $exception->errors());
        } catch (HttpException $exception) {
            self::assertSame('removed', $state);
            self::assertSame(403, $exception->getStatusCode());
        }

        self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
        self::assertSame($auditBefore, DB::table('audit_events')->count());
    }

    public function test_delayed_proof_callback_cannot_bind_confirmation_to_a_different_request_account(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $request = $this->requestFor($user);
        DB::transaction(static function () use ($request, $other): void {
            app(ConfirmAccountPassword::class)->handle($request, 'password');
            $request->setUserResolver(static fn (): User => $other);
        });

        self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
        $this->assertDatabaseHas('audit_events', ['event' => 'auth.password.confirmed', 'actor_user_id' => $user->id]);
        $this->assertDatabaseMissing('audit_events', ['event' => 'auth.password.confirmed', 'actor_user_id' => $other->id]);
    }

    public function test_later_credential_transition_in_the_same_outer_transaction_suppresses_obsolete_proof(): void
    {
        $user = User::factory()->create();
        $request = $this->requestFor($user);
        DB::transaction(static function () use ($user, $request): void {
            app(ConfirmAccountPassword::class)->handle($request, 'password');
            app(ChangePassword::class)->handle((int) $user->id, 'password', 'ChangedPassword123!', null);
        });

        self::assertFalse($request->session()->has('accounts.recent_authentication_at'));
        self::assertTrue(Hash::check('ChangedPassword123!', (string) $user->refresh()->password));
        $this->assertDatabaseHas('audit_events', ['event' => 'auth.password.confirmed', 'actor_user_id' => $user->id]);
    }

    private function requestFor(User $user): Request
    {
        $request = Request::create('/confirm-password', 'POST');
        $request->setUserResolver(static fn (): User => $user);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }
}
