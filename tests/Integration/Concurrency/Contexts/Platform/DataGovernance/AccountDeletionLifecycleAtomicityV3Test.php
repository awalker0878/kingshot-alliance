<?php

declare(strict_types=1);

namespace Tests\Integration\Concurrency\Contexts\Platform\DataGovernance;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Platform\DataGovernance\Actions\CancelAccountDeletion;
use App\Contexts\Platform\DataGovernance\Actions\ProcessAccountDeletionRequests;
use App\Contexts\Platform\DataGovernance\Actions\RequestAccountDeletion;
use App\Contexts\Platform\DataGovernance\Models\AccountDeletionRequest;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class AccountDeletionLifecycleAtomicityV3Test extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    /** @return iterable<string,array{bool}> */
    public static function transitions(): iterable
    {
        yield 'request' => [false];
        yield 'cancel' => [true];
    }

    #[DataProvider('transitions')]
    public function test_notification_persistence_failure_rolls_back_both_owners_and_their_audit(bool $cancel): void
    {
        $user = User::factory()->create();
        if ($cancel) {
            app(RequestAccountDeletion::class)->handle((int) $user->id);
        }
        $before = $this->state($user);
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "notification_messages"')) {
                $failed = true;
                throw new RuntimeException('Injected notification persistence failure.');
            }
        });

        try {
            if ($cancel) {
                app(CancelAccountDeletion::class)->handle((int) $user->id);
            } else {
                app(RequestAccountDeletion::class)->handle((int) $user->id);
            }
            self::fail('The injected notification write failure must reach the caller.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected notification persistence failure.', $exception->getMessage());
        }

        self::assertTrue($failed);
        self::assertSame($before, $this->state($user));
    }

    public function test_repeats_preserve_cooling_off_and_a_new_request_after_cancellation_gets_new_intent(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $request = app(RequestAccountDeletion::class);
        $cancel = app(CancelAccountDeletion::class);
        $id = $request->handle((int) $user->id);
        $first = $this->state($user);
        $this->travel(1)->hours();

        self::assertSame($id, $request->handle((int) $user->id));
        self::assertSame($first, $this->state($user));

        self::assertTrue($cancel->handle((int) $user->id));
        $cancelled = $this->state($user);
        self::assertNull($user->refresh()->deletion_requested_at);
        self::assertFalse($cancel->handle((int) $user->id));
        self::assertSame($cancelled, $this->state($user));

        self::assertSame($id, $request->handle((int) $user->id));
        $current = AccountDeletionRequest::query()->findOrFail($id);
        self::assertSame('pending', $current->status);
        self::assertTrue($current->eligible_at->equalTo(now()->addDays(7)));
        self::assertTrue($user->refresh()->deletion_requested_at?->equalTo(now()));
        self::assertSame(2, DB::table('notification_messages')->where('recipient_user_id', $user->id)->where('subject_id', 'account.deletion_requested')->count());
        self::assertSame(1, DB::table('notification_messages')->where('recipient_user_id', $user->id)->where('subject_id', 'account.deletion_cancelled')->count());

        $current->forceFill(['status' => 'blocked', 'blocked_reason' => 'Current hold.'])->save();
        $blocked = $this->state($user);
        self::assertSame($id, $request->handle((int) $user->id));
        self::assertSame($blocked, $this->state($user));
    }

    public function test_processed_requests_cannot_restore_lifecycle_metadata_or_notifications(): void
    {
        $user = User::factory()->create();
        $id = app(RequestAccountDeletion::class)->handle((int) $user->id);
        AccountDeletionRequest::query()->whereKey($id)->update(['eligible_at' => now()->subMinute()]);
        self::assertSame(1, app(ProcessAccountDeletionRequests::class)->handle());
        $before = $this->state($user);

        self::assertSame($id, app(RequestAccountDeletion::class)->handle((int) $user->id));
        self::assertFalse(app(CancelAccountDeletion::class)->handle((int) $user->id));
        self::assertSame(0, app(ProcessAccountDeletionRequests::class)->handle());

        self::assertSame($before, $this->state($user));
        self::assertNull($user->refresh()->deletion_requested_at);
        self::assertNotNull($user->anonymized_at);
    }

    /** @return iterable<string,array{bool}> */
    public static function serializedTransitions(): iterable
    {
        yield 'request' => [false];
        yield 'process' => [true];
    }

    #[DataProvider('serializedTransitions')]
    public function test_cancellation_contends_on_the_same_account_lock_as_request_and_processing(bool $process): void
    {
        $user = User::factory()->create();
        if ($process) {
            $id = app(RequestAccountDeletion::class)->handle((int) $user->id);
            AccountDeletionRequest::query()->whereKey($id)->update(['eligible_at' => now()->subMinute()]);
        }
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.deletion_competitor', config('database.connections.'.$primary));
        DB::connection('deletion_competitor')->statement("SET lock_timeout = '100ms'");
        $attempted = false;
        $blocked = false;
        DB::listen(static function (QueryExecuted $query) use ($user, $primary, &$attempted, &$blocked): void {
            if ($attempted || $query->connectionName !== $primary || ! str_contains($query->sql, 'from "users"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('deletion_competitor');
            try {
                app(CancelAccountDeletion::class)->handle((int) $user->id);
            } catch (QueryException $exception) {
                self::assertSame('55P03', $exception->errorInfo[0]);
                $blocked = true;
            } finally {
                DB::setDefaultConnection($primary);
            }
        });

        try {
            if ($process) {
                self::assertSame(1, app(ProcessAccountDeletionRequests::class)->handle());
                self::assertNotNull($user->refresh()->anonymized_at);
                self::assertFalse(app(CancelAccountDeletion::class)->handle((int) $user->id));
            } else {
                app(RequestAccountDeletion::class)->handle((int) $user->id);
                self::assertNotNull($user->refresh()->deletion_requested_at);
                self::assertTrue(app(CancelAccountDeletion::class)->handle((int) $user->id));
                self::assertNull($user->refresh()->deletion_requested_at);
            }
            self::assertTrue($attempted);
            self::assertTrue($blocked, 'The competing lifecycle transition must wait for the current account transition.');
        } finally {
            DB::purge('deletion_competitor');
        }
    }

    /** @return array<string,mixed> */
    private function state(User $user): array
    {
        return [
            'account' => $user->refresh()->getRawOriginal(),
            'requests' => AccountDeletionRequest::query()->where('user_id', $user->id)->get()->toArray(),
            'audit' => DB::table('audit_events')->count(),
            'messages' => DB::table('notification_messages')->count(),
            'deliveries' => DB::table('notification_deliveries')->count(),
        ];
    }
}
