<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\Events\Feature;

use App\Contexts\Operations\Events\Actions\CancelEvent;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Actions\UpdateEvent;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\RecurrenceFrequency;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Polls\Actions\SaveEventPoll;
use App\Contexts\Operations\Polls\Enums\EventPollType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class EventScheduleHistoryBoundsTest extends TestCase
{
    use RefreshDatabase;

    private string $actor;

    private string $event;

    private CarbonImmutable $start;

    protected function setUp(): void
    {
        parent::setUp();
        $factory = app(ScenarioFactory::class);
        $actor = $factory->player((int) $factory->authUser()->id, 61737);
        $alliance = $factory->alliance($actor);
        $factory->roster($actor, $alliance);
        $scope = EventTypeScope::query()->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))->firstOrFail();
        $this->actor = $actor->playerId;
        $this->start = CarbonImmutable::now('UTC')->addDays(3)->startOfSecond();
        $this->event = app(CreateEvent::class)->handle($this->actor, (string) $scope->id,
            EventScope::Alliance, $alliance->allianceId, $this->start, durationMinutes: 60,
            frequency: RecurrenceFrequency::Daily)->eventId;
    }

    public function test_large_cancelled_history_is_not_hydrated_and_requested_identities_keep_attached_state(): void
    {
        $history = $this->history(1000);
        $retained = EventOccurrence::query()->where('event_id', $this->event)->where('starts_at', $this->start)->firstOrFail();
        $poll = app(SaveEventPoll::class)->handle($this->actor, (string) $retained->id, 'identity', EventPollType::Choice,
            question: 'Keep attached', options: [['label' => 'A', 'value' => 'a'], ['label' => 'B', 'value' => 'b']]);
        $retained->update(['status' => EventOccurrenceStatus::Cancelled]);
        $loaded = [];
        EventOccurrence::retrieved(static function (EventOccurrence $row) use (&$loaded): void {
            $loaded[] = (string) $row->id;
        });
        app(UpdateEvent::class)->handle($this->actor, $this->event, durationMinutes: 90);
        self::assertCount(64, $loaded);
        self::assertSame([], array_intersect($history, $loaded));
        self::assertSame(64, DB::table('event_occurrences')->where('event_id', $this->event)->where('status', 'scheduled')->count());
        self::assertSame(1000, DB::table('event_occurrences')->where('event_id', $this->event)->where('status', 'cancelled')->count());
        self::assertSame((string) $retained->id, DB::table('event_polls')->where('id', $poll)->value('occurrence_id'));
        self::assertSame('scheduled', DB::table('event_occurrences')->where('id', $retained->id)->value('status'));
        self::assertSame($this->start->addMinutes(90)->format('Y-m-d H:i:s'), DB::table('event_occurrences')->where('id', $retained->id)->value('ends_at'));
    }

    public function test_replacement_of_all_64_dates_preserves_cancelled_history_and_reactivation_identity(): void
    {
        $before = DB::table('event_occurrences')->where('event_id', $this->event)->orderBy('starts_at')->pluck('id')->all();
        app(UpdateEvent::class)->handle($this->actor, $this->event, firstLocalStart: $this->start->addHours(2));
        self::assertSame(64, DB::table('event_occurrences')->whereIn('id', $before)->where('status', 'cancelled')->count());
        self::assertSame(128, DB::table('event_occurrences')->where('event_id', $this->event)->count());
        app(UpdateEvent::class)->handle($this->actor, $this->event, firstLocalStart: $this->start);
        self::assertSame(64, DB::table('event_occurrences')->whereIn('id', $before)->where('status', 'scheduled')->count());
        self::assertSame(128, DB::table('event_occurrences')->where('event_id', $this->event)->count());
    }

    public function test_completed_and_previously_cancelled_rows_keep_their_history_during_edits_and_cancellation(): void
    {
        $completed = EventOccurrence::query()->where('event_id', $this->event)->orderBy('starts_at')->firstOrFail();
        $completed->update(['status' => EventOccurrenceStatus::Completed]);
        $history = $this->history(2);
        $before = DB::table('event_occurrences')->whereIn('id', [...$history, (string) $completed->id])->orderBy('id')->get()->toJson();
        app(UpdateEvent::class)->handle($this->actor, $this->event, durationMinutes: 90);
        app(CancelEvent::class)->handle($this->actor, $this->event);
        self::assertSame($before, DB::table('event_occurrences')->whereIn('id', [...$history, (string) $completed->id])->orderBy('id')->get()->toJson());
        self::assertSame(0, DB::table('event_occurrences')->where('event_id', $this->event)->where('status', 'scheduled')->count());
    }

    public function test_invalid_stored_schedule_rejects_before_partial_mutation(): void
    {
        $this->history(1, 'scheduled');
        $before = $this->state();
        try {
            app(UpdateEvent::class)->handle($this->actor, $this->event, durationMinutes: 90, title: 'Must roll back');
            self::fail('A stored schedule outside the owner generation bound must reject.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('work budget', $exception->getMessage());
        }
        self::assertSame($before, $this->state());
    }

    public function test_late_outbox_failure_rolls_back_reconciliation_and_successful_retry_keeps_identity(): void
    {
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"')) {
                $failed = true;
                throw new RuntimeException('Injected schedule outbox failure');
            }
        });
        try {
            app(UpdateEvent::class)->handle($this->actor, $this->event, firstLocalStart: $this->start->addHours(2));
            self::fail('A late outbox failure must roll back the whole schedule.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected schedule outbox failure', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
        app(UpdateEvent::class)->handle($this->actor, $this->event, firstLocalStart: $this->start->addHours(2));
        self::assertSame(64, DB::table('event_occurrences')->where('event_id', $this->event)->where('status', 'scheduled')->count());
    }

    /** @return iterable<string,array{int}> */
    public static function invalidCapacities(): iterable
    {
        yield 'zero' => [0];
        yield 'above owner limit' => [100001];
    }

    #[DataProvider('invalidCapacities')]
    public function test_update_enforces_the_creation_capacity_range(int $capacity): void
    {
        $before = $this->state();
        try {
            app(UpdateEvent::class)->handle($this->actor, $this->event, capacity: $capacity);
            self::fail('Update must enforce the owner capacity contract.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('capacity', $exception->getMessage());
        }
        self::assertSame($before, $this->state());
    }

    public function test_capacity_uses_exact_database_maximum_across_retained_registration_history(): void
    {
        $history = $this->history(1000);
        $rows = [];
        foreach ($history as $id) {
            $rows[] = ['id' => strtolower((string) Str::ulid()), 'occurrence_id' => $id, 'player_id' => $this->actor,
                'status' => 'registered', 'registered_by_player_id' => $this->actor, 'registered_at' => now()];
        }
        DB::table('event_registrations')->insert($rows);
        $factory = app(ScenarioFactory::class);
        $second = $factory->unclaimedPlayer(61737);
        DB::table('event_registrations')->insert(['id' => strtolower((string) Str::ulid()), 'occurrence_id' => $history[999],
            'player_id' => $second->playerId, 'status' => 'registered', 'registered_by_player_id' => $this->actor, 'registered_at' => now()]);
        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'event_registrations')) {
                $queries[] = $query->sql;
            }
        });
        $before = $this->state();
        try {
            app(UpdateEvent::class)->handle($this->actor, $this->event, capacity: 1);
            self::fail('Capacity must account for the complete retained history.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('registered Player count', $exception->getMessage());
        }
        self::assertSame($before, $this->state());
        self::assertCount(1, $queries);
        self::assertStringContainsString('max(', $queries[0]);
        app(UpdateEvent::class)->handle($this->actor, $this->event, capacity: 2);
        self::assertSame(2, DB::table('events')->where('id', $this->event)->value('capacity'));
    }

    /** @return list<string> */
    private function history(int $count, string $status = 'cancelled'): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $start = $this->start->addDays(100 + $i);
            $rows[] = ['id' => strtolower((string) Str::ulid()), 'event_id' => $this->event, 'starts_at' => $start,
                'ends_at' => $start->addHour(), 'status' => $status, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()];
        }
        DB::table('event_occurrences')->insert($rows);

        return array_column($rows, 'id');
    }

    /** @return array<string,list<string>> */
    private function state(): array
    {
        $state = [];
        foreach (['events', 'event_occurrences', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = array_values(DB::table($table)->orderBy('id')->get()->map(static fn (object $row): string => json_encode($row, JSON_THROW_ON_ERROR))->all());
        }

        return $state;
    }
}
