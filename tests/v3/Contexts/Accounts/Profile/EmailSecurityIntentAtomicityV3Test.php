<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Profile;

use App\Contexts\Accounts\EmailVerification\Notifications\KingshotAllianceEmailChangedNotice;
use App\Contexts\Accounts\EmailVerification\Notifications\VerifyPendingKingshotAllianceEmail;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Profile\Actions\PromotePendingAccountEmail;
use App\Contexts\Accounts\Profile\Actions\RequestAccountEmailChange;
use App\Shared\Infrastructure\Messaging\Outbox\Actions\PublishOutboxBatch;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\v3\TestCase;

final class EmailSecurityIntentAtomicityV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{bool}> */
    public static function mutations(): iterable
    {
        yield 'request' => [false];
        yield 'promote' => [true];
    }

    #[DataProvider('mutations')]
    public function test_failed_security_intent_rolls_back_email_state_and_sends_no_mail(bool $promote): void
    {
        Notification::fake();
        $user = $this->account($promote);
        $before = $this->state($user);
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "notification_messages"')) {
                $failed = true;
                throw new RuntimeException('Injected email security intent failure.');
            }
        });

        try {
            $this->mutate($user, $promote);
            self::fail('Email mutations must persist security intent.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected email security intent failure.', $exception->getMessage());
        }

        self::assertTrue($failed);
        self::assertSame($before, $this->state($user));
        Notification::assertNothingSent();
    }

    #[DataProvider('mutations')]
    public function test_outer_rollback_discards_mail_and_all_email_security_effects(bool $promote): void
    {
        Notification::fake();
        $user = $this->account($promote);
        $before = $this->state($user);

        try {
            DB::transaction(function () use ($user, $promote): void {
                $this->mutate($user, $promote);
                Notification::assertNothingSent();
                throw new RuntimeException('Outer email operation failed.');
            });
            self::fail('Exercise the outer rollback.');
        } catch (RuntimeException $exception) {
            self::assertSame('Outer email operation failed.', $exception->getMessage());
        }

        self::assertSame($before, $this->state($user));
        Notification::assertNothingSent();
    }

    #[DataProvider('mutations')]
    public function test_successful_email_mutation_queues_intent_and_worker_sends_to_the_correct_recipient_after_commit(bool $promote): void
    {
        Notification::fake();
        $user = $this->account($promote);

        DB::transaction(function () use ($user, $promote): void {
            $this->mutate($user, $promote);
            Notification::assertNothingSent();
        });

        Notification::assertNothingSent();
        app(PublishOutboxBatch::class)->handle(100);

        self::assertSame($promote ? 'next@example.test' : 'current@example.test', $user->refresh()->email);
        self::assertSame($promote ? null : 'next@example.test', $user->pending_email);
        self::assertSame(1, DB::table('notification_messages')->where('recipient_user_id', $user->id)->count());
        Notification::assertSentOnDemand(
            $promote ? KingshotAllianceEmailChangedNotice::class : VerifyPendingKingshotAllianceEmail::class,
            static fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['mail']
                && $notifiable->routes['mail'] === ($promote ? 'current@example.test' : 'next@example.test'),
        );
    }

    public function test_a_later_return_to_the_same_email_has_a_new_security_occurrence(): void
    {
        Notification::fake();
        $user = $this->account(false);

        foreach (['next@example.test', 'current@example.test', 'next@example.test'] as $email) {
            app(RequestAccountEmailChange::class)->handle((int) $user->id, $email);
            app(PromotePendingAccountEmail::class)->handle((int) $user->id, sha1($email));
        }

        self::assertSame('next@example.test', $user->refresh()->email);
        self::assertSame(3, DB::table('notification_messages')->where('subject_id', 'auth.email.change_requested')->count());
        self::assertSame(3, DB::table('notification_messages')->where('subject_id', 'auth.email.changed')->count());
    }

    private function account(bool $promote): User
    {
        return User::factory()->create([
            'email' => 'current@example.test',
            'pending_email' => $promote ? 'next@example.test' : null,
            'pending_email_requested_at' => $promote ? now() : null,
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

    /** @return array<string,mixed> */
    private function state(User $user): array
    {
        return [
            'account' => $user->refresh()->getRawOriginal(),
            'audit' => DB::table('audit_events')->count(),
            'outbox' => DB::table('outbox_messages')->count(),
            'messages' => DB::table('notification_messages')->count(),
            'deliveries' => DB::table('notification_deliveries')->count(),
        ];
    }
}
