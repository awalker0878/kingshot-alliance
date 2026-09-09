<?php

declare(strict_types=1);

namespace Tests\Integration\Contexts\Accounts\EmailVerification;

use App\Contexts\Accounts\EmailVerification\Actions\VerifyAccountEmail;
use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class EmailVerificationAtomicityV3Test extends TestCase
{
    use DatabaseTruncation;

    public function test_verification_commits_with_audit_and_emits_one_event_only_after_the_outermost_commit(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();
        DB::transaction(static function () use ($user): void {
            app(VerifyAccountEmail::class)->handle((int) $user->id, sha1((string) $user->email));
            self::assertTrue($user->refresh()->hasVerifiedEmail());
            self::assertSame(1, DB::table('audit_events')->where('event', 'auth.email.verified')->count());
            Event::assertNotDispatched(Verified::class);
        });

        app(VerifyAccountEmail::class)->handle((int) $user->id, sha1((string) $user->email));
        Event::assertDispatchedTimes(Verified::class, 1);
        self::assertSame(1, DB::table('audit_events')->where('event', 'auth.email.verified')->count());
    }

    public function test_audit_insert_failure_rolls_back_verification_without_emitting_the_framework_event(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "audit_events"') && in_array('auth.email.verified', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected verification audit failure.');
            }
        });

        try {
            app(VerifyAccountEmail::class)->handle((int) $user->id, sha1((string) $user->email));
            self::fail('Verification requires its audit record.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected verification audit failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertFalse($user->refresh()->hasVerifiedEmail());
        self::assertSame(0, DB::table('audit_events')->count());
        Event::assertNotDispatched(Verified::class);
    }

    public function test_outer_rollback_discards_verification_audit_and_completion_event(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();

        try {
            DB::transaction(static function () use ($user): void {
                app(VerifyAccountEmail::class)->handle((int) $user->id, sha1((string) $user->email));
                throw new RuntimeException('Later owner failed.');
            });
            self::fail('Exercise the containing rollback.');
        } catch (RuntimeException $exception) {
            self::assertSame('Later owner failed.', $exception->getMessage());
        }
        self::assertFalse($user->refresh()->hasVerifiedEmail());
        self::assertSame(0, DB::table('audit_events')->count());
        Event::assertNotDispatched(Verified::class);
    }

    public function test_real_signed_verification_route_and_replay_use_the_owner_action(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:31::1'])->actingAs($user)->get($url)
            ->assertRedirect(route('dashboard', ['verified' => 1]));
        $this->get($url)->assertRedirect(route('dashboard', ['verified' => 1]));

        self::assertTrue($user->refresh()->hasVerifiedEmail());
        Event::assertDispatchedTimes(Verified::class, 1);
        self::assertSame(1, DB::table('audit_events')->where('event', 'auth.email.verified')->count());
    }

    /** @return iterable<string,array{bool}> */
    public static function changedAccounts(): iterable
    {
        yield 'current address changed' => [false];
        yield 'account finalized' => [true];
    }

    #[DataProvider('changedAccounts')]
    public function test_account_change_after_maintained_form_authorization_cannot_verify_the_new_state(bool $finalize): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);
        $authorized = false;
        $this->app->afterResolving(EmailVerificationRequest::class, static function (EmailVerificationRequest $request) use ($user, $finalize, &$authorized): void {
            self::assertTrue($request->authorize());
            $authorized = true;
            if ($finalize) {
                app(AnonymizeAccount::class)->handle((int) $user->id, 'in-flight-verification');
            } else {
                User::query()->whereKey($user->id)->update(['email' => 'changed@example.test']);
            }
        });

        $response = $this->withServerVariables(['REMOTE_ADDR' => $finalize ? '2001:db8:31::2' : '2001:db8:31::3'])
            ->actingAs($user)->get($url);
        if ($finalize) {
            $response->assertSessionHasErrors('account');
        } else {
            $response->assertForbidden();
        }
        self::assertTrue($authorized);
        self::assertFalse($user->refresh()->hasVerifiedEmail());
        self::assertSame($finalize ? 'deleted+'.$user->id.'@invalid.local' : 'changed@example.test', $user->email);
        self::assertSame(0, DB::table('audit_events')->where('event', 'auth.email.verified')->count());
        Event::assertNotDispatched(Verified::class);
    }

    public function test_tampered_signed_link_never_reaches_verification(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user).'tampered';

        $this->actingAs($user)->get($url)->assertForbidden();
        self::assertFalse($user->refresh()->hasVerifiedEmail());
        self::assertSame(0, DB::table('audit_events')->where('event', 'auth.email.verified')->count());
        Event::assertNotDispatched(Verified::class);
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1((string) $user->email),
        ]);
    }
}
