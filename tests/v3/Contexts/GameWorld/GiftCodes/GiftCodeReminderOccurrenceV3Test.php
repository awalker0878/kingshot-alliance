<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\GameWorld\GiftCodes\Actions\QueueDueGiftCodeReminders;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeAccountStateStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeAccountState;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class GiftCodeReminderOccurrenceV3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-08T12:00:30Z'));
        config()->set('game_world.gift_codes.notification_fanout', true);
    }

    #[DataProvider('rescheduledTimes')]
    public function test_a_stale_sweep_neither_queues_nor_clears_a_replaced_or_cancelled_reminder(?string $replacement): void
    {
        $state = $this->reminder('2026-09-08 12:00:10');
        $changed = false;
        GiftCodeAccountState::retrieved(function (GiftCodeAccountState $snapshot) use ($state, $replacement, &$changed): void {
            if (! $changed && $snapshot->id === $state->id) {
                $changed = true;
                // Deterministically interleave a user mutation after candidate
                // selection but before the worker acquires the occurrence lock.
                DB::table('gift_code_account_states')->where('id', $state->id)->update(['remind_at' => $replacement]);
            }
        });

        self::assertSame(0, app(QueueDueGiftCodeReminders::class)->handle());
        self::assertTrue($changed);
        self::assertSame($replacement, $state->refresh()->remind_at?->format('Y-m-d H:i:s'));
        self::assertSame(0, NotificationMessage::query()->count());
    }

    public static function rescheduledTimes(): iterable
    {
        yield 'new occurrence is already due' => ['2026-09-08 12:00:20'];
        yield 'new occurrence is still future' => ['2026-09-08 12:01:00'];
        yield 'cancelled' => [null];
    }

    public function test_distinct_occurrences_in_the_same_minute_are_not_deduplicated_together(): void
    {
        $state = $this->reminder('2026-09-08 12:00:10');
        $action = app(QueueDueGiftCodeReminders::class);
        self::assertSame(1, $action->handle());
        self::assertSame(0, $action->handle());

        $state->refresh()->forceFill(['remind_at' => '2026-09-08 12:00:20'])->save();
        self::assertSame(1, $action->handle());
        self::assertSame(0, $action->handle());
        self::assertSame(2, NotificationMessage::query()->count());
        self::assertNull($state->refresh()->remind_at);
    }

    public function test_delivery_intent_rolls_back_with_an_unconsumed_occurrence_and_retry_creates_one_message(): void
    {
        $state = $this->reminder('2026-09-08 12:00:10');
        $failed = false;
        GiftCodeAccountState::updating(function (GiftCodeAccountState $updated) use (&$failed): void {
            if (! $failed && $updated->remind_at === null) {
                $failed = true;
                throw new RuntimeException('Simulated failure while acknowledging reminder occurrence.');
            }
        });

        try {
            app(QueueDueGiftCodeReminders::class)->handle();
            self::fail('Expected the simulated acknowledgement failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated failure while acknowledging reminder occurrence.', $exception->getMessage());
        }
        self::assertSame(0, NotificationMessage::query()->count());
        self::assertNotNull($state->refresh()->remind_at);
        self::assertSame(1, app(QueueDueGiftCodeReminders::class)->handle());
        self::assertSame(0, app(QueueDueGiftCodeReminders::class)->handle());
        self::assertSame(1, NotificationMessage::query()->count());
    }

    public function test_the_exact_due_boundary_is_consumed_and_no_governor_means_no_delivery(): void
    {
        $state = $this->reminder('2026-09-08 12:00:30');
        self::assertSame(1, app(QueueDueGiftCodeReminders::class)->handle());
        self::assertNull($state->refresh()->remind_at);

        $unowned = $this->reminder('2026-09-08 12:00:20', false);
        self::assertSame(0, app(QueueDueGiftCodeReminders::class)->handle());
        self::assertNull($unowned->refresh()->remind_at);
        self::assertSame(1, NotificationMessage::query()->count());
    }

    private function reminder(string $at, bool $ownsGovernor = true): GiftCodeAccountState
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        if ($ownsGovernor) {
            $scenarios->player($account->userId, 2301, 'REMINDER');
        }
        $code = GiftCode::query()->create([
            'code' => 'REMINDER-'.$account->userId,
            'normalized_code' => 'REMINDER-'.$account->userId,
            'status' => GiftCodeStatus::Valid,
            'status_revision' => 1,
            'status_reason_code' => 'qualified_positive_evidence',
            'status_evidence_ids' => [],
            'status_changed_at' => now(),
            'status_derived_at' => now(),
            'discovered_at' => now(),
            'expires_revision' => 0,
        ]);

        return GiftCodeAccountState::query()->create([
            'gift_code_id' => $code->id,
            'user_id' => $account->userId,
            'state' => GiftCodeAccountStateStatus::Actionable,
            'remind_at' => $at,
        ]);
    }
}
