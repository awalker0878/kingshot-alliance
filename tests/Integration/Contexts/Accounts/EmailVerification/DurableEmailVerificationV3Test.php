<?php

declare(strict_types=1);

namespace Tests\Integration\Contexts\Accounts\EmailVerification;

use App\Contexts\Accounts\EmailVerification\Actions\RequestEmailVerification;
use App\Contexts\Accounts\EmailVerification\Enums\EmailVerificationTarget;
use App\Contexts\Accounts\EmailVerification\Notifications\VerifyKingshotAllianceEmail;
use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Registration\Actions\RegisterUser;
use App\Shared\Infrastructure\Messaging\Outbox\Actions\PublishOutboxBatch;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class DurableEmailVerificationV3Test extends TestCase
{
    use DatabaseTruncation;

    public function test_registration_saves_delivery_intent_without_contacting_mail_and_the_worker_sends_later(): void
    {
        Notification::fake();
        $account = app(RegisterUser::class)->handle('Verification fixture', 'registration@example.test', 'Strong-Password-123!');
        $user = User::query()->findOrFail($account->userId);
        $intent = OutboxMessage::query()->where('event_type', RequestEmailVerification::EVENT_TYPE)->sole();

        self::assertSame((string) $user->id, $intent->aggregate_id);
        self::assertSame(['target' => 'account', 'email_hash' => hash('sha256', (string) $user->email)], $intent->payload);
        self::assertNull($intent->published_at);
        Notification::assertNothingSent();

        app(PublishOutboxBatch::class)->handle(100);

        Notification::assertSentToTimes($user, VerifyKingshotAllianceEmail::class, 1);
        self::assertNotNull($intent->refresh()->published_at);
        app(PublishOutboxBatch::class)->handle(100);
        Notification::assertSentToTimes($user, VerifyKingshotAllianceEmail::class, 1);
    }

    public function test_intent_insert_failure_rolls_back_registration_instead_of_leaving_an_unmailed_account(): void
    {
        Notification::fake();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"')
                && in_array(RequestEmailVerification::EVENT_TYPE, $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected verification intent failure.');
            }
        });

        try {
            app(RegisterUser::class)->handle('Rolled back fixture', 'rollback@example.test', 'Strong-Password-123!');
            self::fail('Registration must persist verification intent.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected verification intent failure.', $exception->getMessage());
        }

        self::assertTrue($failed);
        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.test']);
        self::assertSame(0, OutboxMessage::query()->count());
        self::assertSame(0, DB::table('audit_events')->count());
        Notification::assertNothingSent();
    }

    public function test_mail_failure_uses_durable_backoff_and_retry_outside_every_transaction(): void
    {
        $this->freezeSecond();
        $user = User::factory()->unverified()->create();
        app(RequestEmailVerification::class)->handle((int) $user->id, EmailVerificationTarget::Account);
        $intent = OutboxMessage::query()->sole();
        $attempts = 0;
        Notification::shouldReceive('send')->twice()->andReturnUsing(static function (User $recipient, VerifyKingshotAllianceEmail $notification) use (&$attempts, $user): void {
            self::assertSame(0, DB::transactionLevel());
            self::assertSame((int) $user->id, (int) $recipient->id);
            $attempts++;
            if ($attempts === 1) {
                throw new RuntimeException('Injected verification transport failure.');
            }
        });
        $publisher = app(PublishOutboxBatch::class);

        self::assertSame(0, $publisher->handle(1));
        self::assertNull($intent->refresh()->published_at);
        self::assertSame(1, $intent->attempts);
        self::assertSame('Injected verification transport failure.', $intent->last_error);
        self::assertTrue($intent->available_at->equalTo(now()->addMinute()));
        self::assertSame(0, $publisher->handle(1));
        self::assertSame(1, $attempts);
        $this->travel(60)->seconds();

        self::assertSame(1, $publisher->handle(1));
        self::assertNotNull($intent->refresh()->published_at);
        self::assertNull($intent->last_error);
        self::assertSame(2, $intent->attempts);
    }

    /** @return iterable<string,array{string}> */
    public static function staleAccounts(): iterable
    {
        yield 'verified' => ['verified'];
        yield 'address changed' => ['address'];
        yield 'anonymized' => ['anonymized'];
        yield 'removed' => ['removed'];
    }

    #[DataProvider('staleAccounts')]
    public function test_delivery_suppresses_stale_verified_or_finalized_account_intent(string $change): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        app(RequestEmailVerification::class)->handle((int) $user->id, EmailVerificationTarget::Account);
        $intent = OutboxMessage::query()->sole();
        match ($change) {
            'verified' => $user->forceFill(['email_verified_at' => now()])->save(),
            'address' => $user->forceFill(['email' => 'changed@example.test'])->save(),
            'anonymized' => app(AnonymizeAccount::class)->handle((int) $user->id, 'verification-finalization'),
            'removed' => $user->delete(),
        };

        self::assertSame(1, app(PublishOutboxBatch::class)->handle(1));
        self::assertNotNull($intent->refresh()->published_at);
        Notification::assertNothingSent();
    }

    public function test_resend_http_queues_current_unverified_email_and_verified_accounts_create_no_intent(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->post('/email/verification-notification')
            ->assertRedirect()->assertSessionHas('status', 'verification-link-sent');

        self::assertSame(1, OutboxMessage::query()->where('event_type', RequestEmailVerification::EVENT_TYPE)->count());
        Notification::assertNothingSent();
        $user->forceFill(['email_verified_at' => now()])->save();
        app(RequestEmailVerification::class)->handle((int) $user->id, EmailVerificationTarget::Account);
        self::assertSame(1, OutboxMessage::query()->where('event_type', RequestEmailVerification::EVENT_TYPE)->count());
    }

    public function test_delayed_delivery_generates_a_current_expiring_signature_with_the_existing_branded_mail(): void
    {
        $this->freezeSecond();
        $user = User::factory()->unverified()->create();
        app(RequestEmailVerification::class)->handle((int) $user->id, EmailVerificationTarget::Account);
        $this->travel(2)->hours();
        $mail = null;
        Notification::shouldReceive('send')->once()->andReturnUsing(static function (User $recipient, VerifyKingshotAllianceEmail $notification) use (&$mail): void {
            $mail = $notification->toMail($recipient);
        });

        $intent = OutboxMessage::query()->sole();
        self::assertSame(1, app(PublishOutboxBatch::class)->handle(1), (string) $intent->refresh()->last_error);
        self::assertNotNull($mail);
        self::assertSame(['html' => 'mail.accounts.security', 'text' => 'mail.accounts.security-text'], $mail->view);
        $url = $mail->viewData['actionUrl'];
        $request = Request::create($url);
        self::assertTrue(URL::hasValidSignature($request));
        self::assertSame(now()->addMinutes((int) config('auth.verification.expire', 60))->timestamp, (int) $request->query('expires'));
        self::assertStringContainsString('/verify-email/'.$user->id.'/'.sha1((string) $user->email), $url);
        foreach ($mail->view as $format => $view) {
            $rendered = view($view, $mail->viewData)->render();
            self::assertStringContainsString('KINGSHOT ALLIANCE', $rendered);
            self::assertStringContainsString($format === 'text' ? $url : e($url), $rendered);
        }

    }
}
