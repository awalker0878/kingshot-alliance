<?php

declare(strict_types=1);

namespace Tests\Integration\Contexts\Accounts\EmailVerification;

use App\Contexts\Accounts\EmailVerification\Actions\RequestEmailVerification;
use App\Contexts\Accounts\EmailVerification\Notifications\KingshotAllianceEmailChangedNotice;
use App\Contexts\Accounts\EmailVerification\Notifications\VerifyPendingKingshotAllianceEmail;
use App\Contexts\Accounts\EmailVerification\Services\EmailChangedNoticeOutbox;
use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Profile\Actions\PromotePendingAccountEmail;
use App\Contexts\Accounts\Profile\Actions\RequestAccountEmailChange;
use App\Shared\Infrastructure\Messaging\Outbox\Actions\PublishOutboxBatch;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class DurableEmailChangeV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{bool,string}> */
    public static function mutations(): iterable
    {
        yield 'pending verification' => [false, RequestEmailVerification::EVENT_TYPE];
        yield 'old-address notice' => [true, EmailChangedNoticeOutbox::EVENT_TYPE];
    }

    #[DataProvider('mutations')]
    public function test_delivery_intent_failure_rolls_back_profile_audit_and_security_messages(bool $promote, string $eventType): void
    {
        Notification::fake();
        $user = $this->account();
        $before = $user->refresh()->getRawOriginal();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use ($eventType, &$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"')
                && in_array($eventType, $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected email-change delivery intent failure.');
            }
        });

        try {
            $this->mutate($user, $promote);
            self::fail('Every email transition must persist its delivery intent.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected email-change delivery intent failure.', $exception->getMessage());
        }

        self::assertTrue($failed);
        self::assertSame($before, $user->refresh()->getRawOriginal());
        self::assertSame(0, OutboxMessage::query()->count());
        self::assertSame(0, DB::table('audit_events')->count());
        self::assertSame(0, DB::table('notification_messages')->count());
        self::assertSame(0, DB::table('notification_deliveries')->count());
        Notification::assertNothingSent();
    }

    #[DataProvider('mutations')]
    public function test_both_email_change_deliveries_retry_smtp_failure_after_committed_intent(bool $promote, string $eventType): void
    {
        $this->freezeSecond();
        $user = $this->account();
        $attempts = 0;
        Notification::shouldReceive('send')->twice()->andReturnUsing(static function (AnonymousNotifiable $recipient, LaravelNotification $notification) use ($promote, &$attempts): void {
            self::assertSame(0, DB::transactionLevel());
            self::assertSame($promote ? 'current@example.test' : 'next@example.test', $recipient->routes['mail']);
            self::assertInstanceOf($promote ? KingshotAllianceEmailChangedNotice::class : VerifyPendingKingshotAllianceEmail::class, $notification);
            if (++$attempts === 1) {
                throw new RuntimeException('Injected email-change transport failure.');
            }
        });

        $this->mutate($user, $promote);
        self::assertSame(0, $attempts, 'The account operation must not contact SMTP.');
        $intent = OutboxMessage::query()->where('event_type', $eventType)->sole();
        self::assertSame(0, app(PublishOutboxBatch::class)->handle(1));
        self::assertNull($intent->refresh()->published_at);
        self::assertSame(1, $intent->attempts);
        $this->travel(60)->seconds();

        self::assertSame(1, app(PublishOutboxBatch::class)->handle(1));
        self::assertNotNull($intent->refresh()->published_at);
        self::assertSame(2, $attempts);
    }

    public function test_superseded_pending_address_receives_no_verification_but_the_current_one_does(): void
    {
        Notification::fake();
        $user = $this->account();
        app(RequestAccountEmailChange::class)->handle((int) $user->id, 'superseded@example.test');
        app(RequestAccountEmailChange::class)->handle((int) $user->id, 'latest@example.test');

        self::assertSame(2, app(PublishOutboxBatch::class)->handle(100));

        Notification::assertSentOnDemandTimes(VerifyPendingKingshotAllianceEmail::class, 1);
        Notification::assertSentOnDemand(VerifyPendingKingshotAllianceEmail::class, static fn ($notification, array $channels, AnonymousNotifiable $recipient): bool => $recipient->routes['mail'] === 'latest@example.test');
        self::assertSame('current@example.test', $user->refresh()->email);
    }

    public function test_promoted_pending_address_drops_stale_verification_and_sends_the_old_address_notice(): void
    {
        Notification::fake();
        $user = $this->account();
        app(RequestAccountEmailChange::class)->handle((int) $user->id, 'next@example.test');
        app(PromotePendingAccountEmail::class)->handle((int) $user->id, sha1('next@example.test'));

        self::assertSame(2, app(PublishOutboxBatch::class)->handle(100));

        Notification::assertSentOnDemandTimes(VerifyPendingKingshotAllianceEmail::class, 0);
        Notification::assertSentOnDemandTimes(KingshotAllianceEmailChangedNotice::class, 1);
        Notification::assertSentOnDemand(KingshotAllianceEmailChangedNotice::class, static fn ($notification, array $channels, AnonymousNotifiable $recipient): bool => $recipient->routes['mail'] === 'current@example.test');
    }

    public function test_notice_recipient_is_encrypted_and_finalization_removes_only_its_accounts_delivery_data(): void
    {
        Notification::fake();
        $user = $this->account();
        $other = User::factory()->create(['email' => 'other@example.test', 'pending_email' => 'other-next@example.test']);
        app(PromotePendingAccountEmail::class)->handle((int) $user->id, sha1('next@example.test'));
        app(PromotePendingAccountEmail::class)->handle((int) $other->id, sha1('other-next@example.test'));
        $intent = OutboxMessage::query()->where('aggregate_id', (string) $user->id)->sole();
        $stored = json_encode($intent->payload, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('current@example.test', $stored);
        self::assertStringNotContainsString('next@example.test', $stored);

        app(AnonymizeAccount::class)->handle((int) $user->id, 'email-data-finalization');

        $this->assertDatabaseMissing('outbox_messages', ['id' => $intent->id]);
        self::assertSame(1, OutboxMessage::query()->where('event_type', EmailChangedNoticeOutbox::EVENT_TYPE)->count());
        self::assertSame(1, app(PublishOutboxBatch::class)->handle(100));
        Notification::assertSentOnDemandTimes(KingshotAllianceEmailChangedNotice::class, 1);
        Notification::assertSentOnDemand(KingshotAllianceEmailChangedNotice::class, static fn ($notification, array $channels, AnonymousNotifiable $recipient): bool => $recipient->routes['mail'] === 'other@example.test');
    }

    public function test_delayed_notice_preserves_the_historical_change_even_if_the_account_changes_again(): void
    {
        $user = $this->account();
        app(PromotePendingAccountEmail::class)->handle((int) $user->id, sha1('next@example.test'));
        $user->refresh()->forceFill(['email' => 'later@example.test'])->save();
        Notification::shouldReceive('send')->once()->andReturnUsing(static function (AnonymousNotifiable $recipient, KingshotAllianceEmailChangedNotice $notification): void {
            self::assertSame('current@example.test', $recipient->routes['mail']);
            $mail = $notification->toMail($recipient);
            self::assertStringContainsString('next@example.test', $mail->viewData['intro']);
            self::assertStringNotContainsString('later@example.test', $mail->viewData['intro']);
        });

        self::assertSame(1, app(PublishOutboxBatch::class)->handle(100));
    }

    private function account(): User
    {
        return User::factory()->create([
            'email' => 'current@example.test', 'pending_email' => 'next@example.test', 'pending_email_requested_at' => now(),
        ]);
    }

    private function mutate(User $user, bool $promote): void
    {
        if ($promote) {
            app(PromotePendingAccountEmail::class)->handle((int) $user->id, sha1('next@example.test'));
        } else {
            app(RequestAccountEmailChange::class)->handle((int) $user->id, 'next@example.test');
        }
    }
}
