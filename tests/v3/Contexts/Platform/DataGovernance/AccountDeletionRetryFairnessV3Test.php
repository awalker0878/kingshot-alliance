<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Platform\DataGovernance;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Platform\DataGovernance\Actions\CancelAccountDeletion;
use App\Contexts\Platform\DataGovernance\Actions\ProcessAccountDeletionRequests;
use App\Contexts\Platform\DataGovernance\Actions\RequestAccountDeletion;
use App\Contexts\Platform\DataGovernance\Models\AccountDeletionRequest;
use App\Contexts\Platform\DataGovernance\Models\LegalHold;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\v3\TestCase;

final class AccountDeletionRetryFairnessV3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeSecond();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_a_full_blocked_batch_does_not_starve_a_later_request_even_when_retries_are_due(): void
    {
        $first = $this->request(true, 3);
        $second = $this->request(true, 2);
        $ready = $this->request(false, 1);
        $worker = app(ProcessAccountDeletionRequests::class);

        self::assertSame(0, $worker->handle(2));
        self::assertSame('blocked', $first->refresh()->status);
        self::assertSame('blocked', $second->refresh()->status);
        self::assertSame('pending', $ready->refresh()->status);
        self::assertTrue($first->next_attempt_at?->equalTo(now()->addHour()));
        self::assertTrue($second->next_attempt_at?->equalTo(now()->addHour()));

        $this->travel(1)->hours();
        self::assertSame(1, $worker->handle(2));
        self::assertSame('processed', $ready->refresh()->status);
        self::assertNotNull(User::query()->findOrFail($ready->user_id)->anonymized_at);
        self::assertNull(User::query()->findOrFail($first->user_id)->anonymized_at);
        self::assertNull(User::query()->findOrFail($second->user_id)->anonymized_at);
    }

    public function test_a_removed_blocker_retries_at_its_durable_due_time_and_preserves_the_cooling_off_deadline(): void
    {
        $request = $this->request(true, 1);
        $eligibleAt = $request->eligible_at->toIso8601String();
        $worker = app(ProcessAccountDeletionRequests::class);
        self::assertSame(0, $worker->handle());
        $request->refresh();
        self::assertSame($eligibleAt, $request->eligible_at->toIso8601String());
        $retryAt = $request->next_attempt_at;
        self::assertNotNull($retryAt);
        LegalHold::query()->where('subject_id', (string) $request->user_id)->update(['released_at' => now()]);

        self::assertSame(0, $worker->handle());
        $this->travelTo($retryAt->copy()->subSecond());
        self::assertSame(0, $worker->handle());
        $this->travelTo($retryAt);
        self::assertSame(1, $worker->handle());

        $request->refresh();
        self::assertSame('processed', $request->status);
        self::assertSame($eligibleAt, $request->eligible_at->toIso8601String());
        self::assertNull($request->next_attempt_at);
        self::assertNull($request->blocked_reason);
        self::assertSame(0, $worker->handle());
    }

    public function test_cancel_and_re_request_clear_retry_state_without_bypassing_a_new_cooling_off_period(): void
    {
        $request = $this->request(true, 1);
        self::assertSame(0, app(ProcessAccountDeletionRequests::class)->handle());
        self::assertNotNull($request->refresh()->next_attempt_at);

        self::assertTrue(app(CancelAccountDeletion::class)->handle($request->user_id));
        self::assertSame('cancelled', $request->refresh()->status);
        self::assertNull($request->next_attempt_at);
        self::assertSame($request->id, app(RequestAccountDeletion::class)->handle($request->user_id));

        $request->refresh();
        self::assertSame('pending', $request->status);
        self::assertNull($request->next_attempt_at);
        self::assertTrue($request->eligible_at->equalTo(now()->addDays(7)));
        LegalHold::query()->where('subject_id', (string) $request->user_id)->update(['released_at' => now()]);
        self::assertSame(0, app(ProcessAccountDeletionRequests::class)->handle());
        self::assertNull(User::query()->findOrFail($request->user_id)->anonymized_at);
    }

    public function test_retry_deferral_after_batch_selection_is_revalidated_under_the_request_lock(): void
    {
        $request = $this->request(false, 1);
        $deferred = false;
        DB::listen(static function (QueryExecuted $query) use ($request, &$deferred): void {
            if ($deferred || ! str_starts_with($query->sql, 'select * from "account_deletion_requests"') || str_contains($query->sql, 'for update')) {
                return;
            }
            $deferred = true;
            DB::table('account_deletion_requests')->where('id', $request->id)
                ->update(['next_attempt_at' => now()->addHour()]);
        });

        self::assertSame(0, app(ProcessAccountDeletionRequests::class)->handle());
        self::assertTrue($deferred);
        self::assertSame('pending', $request->refresh()->status);
        self::assertNull($request->processed_at);
        self::assertNull(User::query()->findOrFail($request->user_id)->anonymized_at);
    }

    private function request(bool $blocked, int $daysOverdue): AccountDeletionRequest
    {
        $user = User::factory()->create();
        if ($blocked) {
            LegalHold::query()->create([
                'subject_type' => 'user',
                'subject_id' => (string) $user->id,
                'reason' => 'Deletion retry fixture.',
                'placed_by_user_id' => $user->id,
                'placed_at' => now(),
            ]);
        }

        return AccountDeletionRequest::query()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'requested_at' => now()->subDays($daysOverdue + 7),
            'eligible_at' => now()->subDays($daysOverdue),
        ]);
    }
}
