<?php

declare(strict_types=1);

namespace Tests\Contexts\Accounts\Identity\Integration;

use App\Contexts\Accounts\Authentication\Actions\RecordAccountSession;
use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Platform\DataGovernance\Actions\ProcessAccountDeletionRequests;
use App\Contexts\Platform\DataGovernance\Actions\RequestAccountDeletion;
use App\Contexts\Platform\DataGovernance\Models\AccountDeletionRequest;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use SessionHandlerInterface;
use Tests\TestCase;

final class AccountFinalizationSessionCommitV3Test extends TestCase
{
    use DatabaseTruncation;

    public function test_raw_cleanup_observes_committed_terminal_state_and_waits_for_the_outermost_commit(): void
    {
        $user = User::factory()->create();
        self::assertTrue(app(RecordAccountSession::class)->handle((int) $user->id, 'finalized-browser', 'Chrome/'));
        $observed = [];
        $this->useSessionHandler(static function (string $id) use ($user, &$observed): bool {
            $observed[] = [
                'session' => $id,
                'transaction_level' => DB::transactionLevel(),
                'finalized' => $user->refresh()->anonymized_at !== null,
                'sessions' => DB::table('account_sessions')->where('user_id', $user->id)->count(),
            ];

            return true;
        });

        DB::transaction(static function () use ($user, &$observed): void {
            app(AnonymizeAccount::class)->handle((int) $user->id, 'outer-finalization');
            self::assertNotNull($user->refresh()->anonymized_at);
            self::assertSame([], $observed);
        });

        self::assertSame([['session' => 'finalized-browser', 'transaction_level' => 0, 'finalized' => true, 'sessions' => 0]], $observed);
        app(AnonymizeAccount::class)->handle((int) $user->id, 'repeated-finalization');
        app(AnonymizeAccount::class)->handle((int) $user->id + 1000, 'missing-account');
        self::assertCount(1, $observed);
        self::assertFalse(app(RecordAccountSession::class)->handle((int) $user->id, 'finalized-browser', 'Chrome/'));
    }

    public function test_later_platform_outbox_failure_rolls_back_account_request_and_sessions_without_raw_cleanup(): void
    {
        $user = User::factory()->create();
        $request = $this->dueRequest($user);
        self::assertTrue(app(RecordAccountSession::class)->handle((int) $user->id, 'rollback-browser', 'Chrome/'));
        $before = $user->refresh()->getRawOriginal();
        $requestBefore = $request->getRawOriginal();
        $auditBefore = DB::table('audit_events')->count();
        $outboxBefore = DB::table('outbox_messages')->count();
        $destroyed = [];
        $this->useSessionHandler(static function (string $id) use (&$destroyed): bool {
            $destroyed[] = $id;

            return true;
        });
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"') && in_array('platform.account-deletion.processed', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected finalization owner intent failure.');
            }
        });

        try {
            app(ProcessAccountDeletionRequests::class)->handle();
            self::fail('The containing Platform intent must commit with finalization.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected finalization owner intent failure.', $exception->getMessage());
        }

        self::assertTrue($failed);
        self::assertSame([], $destroyed);
        self::assertSame($before, $user->refresh()->getRawOriginal());
        self::assertSame($requestBefore, $request->refresh()->getRawOriginal());
        self::assertSame($auditBefore, DB::table('audit_events')->count());
        self::assertSame($outboxBefore, DB::table('outbox_messages')->count());
        $this->assertDatabaseHas('account_sessions', ['user_id' => $user->id, 'session_id_hash' => hash('sha256', 'rollback-browser'), 'revoked_at' => null]);
        self::assertSame('rollback-browser', AccountSession::query()->where('user_id', $user->id)->sole()->session_id);
    }

    public function test_failed_raw_cleanup_does_not_block_later_sessions_or_accounts_and_cannot_restore_access(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $foreign = User::factory()->create();
        $firstRequest = $this->dueRequest($first);
        $secondRequest = $this->dueRequest($second);
        foreach ([$first->id => ['first-browser', 'second-browser'], $second->id => ['third-browser'], $foreign->id => ['foreign-browser']] as $userId => $ids) {
            foreach ($ids as $id) {
                self::assertTrue(app(RecordAccountSession::class)->handle((int) $userId, $id, 'Chrome/'));
            }
        }
        $observed = [];
        $this->useSessionHandler(static function (string $id) use ($firstRequest, $secondRequest, &$observed): bool {
            $request = $id === 'third-browser' ? $secondRequest : $firstRequest;
            $observed[$id] = [DB::transactionLevel(), $request->refresh()->status];
            if ($id === 'first-browser') {
                throw new RuntimeException('Injected finalization session storage failure.');
            }

            return true;
        });

        self::assertSame(2, app(ProcessAccountDeletionRequests::class)->handle());
        ksort($observed);
        self::assertSame(['first-browser' => [0, 'processed'], 'second-browser' => [0, 'processed'], 'third-browser' => [0, 'processed']], $observed);
        self::assertNotNull($first->refresh()->anonymized_at);
        self::assertNotNull($second->refresh()->anonymized_at);
        self::assertNull($foreign->refresh()->anonymized_at);
        self::assertSame(1, DB::table('account_sessions')->count());
        $this->assertDatabaseHas('account_sessions', ['user_id' => $foreign->id, 'session_id_hash' => hash('sha256', 'foreign-browser')]);
        self::assertSame('foreign-browser', AccountSession::query()->where('user_id', $foreign->id)->sole()->session_id);
        self::assertFalse(app(RecordAccountSession::class)->handle((int) $first->id, 'first-browser', 'Chrome/'));
        self::assertSame(0, app(ProcessAccountDeletionRequests::class)->handle());
    }

    private function dueRequest(User $user): AccountDeletionRequest
    {
        $id = app(RequestAccountDeletion::class)->handle((int) $user->id);
        $request = AccountDeletionRequest::query()->findOrFail($id);
        $request->forceFill(['eligible_at' => now()->subMinute()])->save();

        return $request;
    }

    /** @param callable(string):bool $destroy */
    private function useSessionHandler(callable $destroy): void
    {
        $handler = Mockery::mock(SessionHandlerInterface::class);
        $handler->shouldReceive('destroy')->andReturnUsing($destroy);
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getHandler')->andReturn($handler);
        $manager = Mockery::mock(SessionManager::class);
        $manager->shouldReceive('driver')->andReturn($store);
        $this->app->instance(SessionManager::class, $manager);
    }
}
