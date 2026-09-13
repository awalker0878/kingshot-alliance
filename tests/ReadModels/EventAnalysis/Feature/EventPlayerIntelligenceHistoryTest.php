<?php

declare(strict_types=1);

namespace Tests\ReadModels\EventAnalysis\Feature;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Participation\Models\EventAttendance;
use App\Contexts\Operations\Participation\Models\EventRegistration;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use App\ReadModels\EventAnalysis\Queries\EventPlayerIntelligenceQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class EventPlayerIntelligenceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private PlayerReference $actor;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $scenario = app(ScenarioFactory::class);
        $user = $scenario->authUser();
        $this->actor = $scenario->player((int) $user->id, 62736);
        $alliance = $scenario->alliance($this->actor);
        $scenario->roster($this->actor, $alliance);
        $scope = EventTypeScope::query()->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))->firstOrFail();
        $created = app(CreateEvent::class)->handle($this->actor->playerId, (string) $scope->id, EventScope::Alliance,
            $alliance->allianceId, CarbonImmutable::now('UTC')->addDay(), durationMinutes: 30);
        $this->event = Event::query()->findOrFail($created->eventId);
    }

    public function test_set_union_and_completed_excused_absent_priority_match_recorded_owner_facts(): void
    {
        [$a, $b, $c, $d, $e, $f] = $this->history(6);
        foreach ([$a, $b, $d] as $id) {
            $this->registration($id, 'registered');
        }
        $this->registration($f, 'cancelled');
        $this->roster($a, 'confirmed');
        $this->rally($a, 'absent');
        $this->rally($a, 'participated');
        $this->attendance($a, 'absent');
        $this->roster($b, 'absent');
        $this->attendance($b, 'excused');
        $this->roster($c, 'absent');
        $this->rally($c, 'confirmed');
        $this->roster($d, 'confirmed');
        $this->attendance($e, 'present');
        $this->roster($f, 'declined');
        $this->rally($f, 'assigned');
        $this->attendance($f, 'unknown');
        $facts = app(EventPlayerIntelligenceQuery::class)->forPlayer($this->event, $this->actor);
        self::assertSame(4, $facts['commitments']);
        self::assertSame(2, $facts['completed']);
        self::assertSame(1, $facts['absent']);
        self::assertSame(1, $facts['excused']);
        self::assertSame(1, $facts['unresolved']);
        self::assertSame(66.7, $facts['reliabilityPercent']);
        self::assertNull($facts['averageScore']);
        self::assertSame(0, $facts['resultCount']);
    }

    public function test_thousands_of_retained_occurrences_use_three_aggregate_queries_and_no_history_models(): void
    {
        $ids = $this->history(2001);
        $registrations = $attendance = $scores = [];
        foreach ($ids as $i => $id) {
            $registrations[] = ['id' => strtolower((string) Str::ulid()), 'occurrence_id' => $id,
                'player_id' => $this->actor->playerId, 'status' => 'registered',
                'registered_by_player_id' => $this->actor->playerId, 'registered_at' => now()];
            $attendance[] = ['id' => strtolower((string) Str::ulid()), 'occurrence_id' => $id,
                'player_id' => $this->actor->playerId, 'status' => $i % 2 === 0 ? 'present' : 'absent',
                'recorded_by_player_id' => $this->actor->playerId, 'recorded_at' => now()];
            $scores[] = ['id' => strtolower((string) Str::ulid()), 'occurrence_id' => $id,
                'player_id' => $this->actor->playerId, 'score' => $i, 'recorded_at' => now()->startOfHour()->addSeconds($i)];
        }
        foreach (array_chunk($registrations, 500) as $rows) {
            DB::table('event_registrations')->insert($rows);
        }
        foreach (array_chunk($attendance, 500) as $rows) {
            DB::table('event_attendance')->insert($rows);
        }
        foreach (array_chunk($scores, 500) as $rows) {
            DB::table('event_player_results')->insert($rows);
        }
        $hydrated = 0;
        foreach ([Event::class, EventOccurrence::class, EventRegistration::class, EventAttendance::class,
            EventRosterMember::class, RallyAssignment::class, EventPlayerResult::class] as $model) {
            $model::retrieved(static function () use (&$hydrated): void {
                $hydrated++;
            });
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $facts = app(EventPlayerIntelligenceQuery::class)->forPlayer($this->event, $this->actor);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        self::assertCount(3, $queries);
        self::assertSame(0, $hydrated);
        self::assertSame(2001, $facts['commitments']);
        self::assertSame(1001, $facts['completed']);
        self::assertSame(1000, $facts['absent']);
        self::assertSame(0, $facts['unresolved']);
        self::assertSame(2001, $facts['resultCount']);
        self::assertSame(1000, $facts['averageScore']);
        self::assertSame(2000, $facts['bestScore']);
        self::assertSame(2000, $facts['latestScore']);
    }

    public function test_score_scope_time_bound_and_exact_average_do_not_mix_foreign_or_future_results(): void
    {
        [$a, $b] = $this->history(2);
        foreach ([$a => PHP_INT_MAX, $b => PHP_INT_MAX - 1] as $id => $score) {
            EventPlayerResult::query()->create(['occurrence_id' => $id, 'player_id' => $this->actor->playerId,
                'score' => $score, 'recorded_at' => now()]);
        }
        $future = (string) $this->event->occurrences()->where('ends_at', '>', now())->value('id');
        EventPlayerResult::query()->create(['occurrence_id' => $future, 'player_id' => $this->actor->playerId, 'score' => 0, 'recorded_at' => now()]);
        $different = EventTypeScope::query()->where('scope', EventScope::Alliance->value)
            ->where('id', '!=', $this->event->event_type_scope_id)->firstOrFail();
        $otherType = $this->event->replicate();
        $otherType->forceFill(['event_type_scope_id' => $different->id, 'event_type_id' => $different->event_type_id])->save();
        $pastOther = EventOccurrence::query()->create(['event_id' => $otherType->id,
            'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHour(), 'status' => 'completed']);
        EventPlayerResult::query()->create(['occurrence_id' => $pastOther->id, 'player_id' => $this->actor->playerId, 'score' => 0, 'recorded_at' => now()]);
        $this->attendance((string) $pastOther->id, 'present');
        $foreignUser = app(ScenarioFactory::class)->authUser();
        $foreignOwner = app(ScenarioFactory::class)->player((int) $foreignUser->id, 62736);
        $foreignAlliance = app(ScenarioFactory::class)->alliance($foreignOwner);
        $foreign = $this->event->replicate();
        $foreign->forceFill(['alliance_id' => $foreignAlliance->allianceId])->save();
        $foreignOccurrence = EventOccurrence::query()->create(['event_id' => $foreign->id,
            'starts_at' => now()->subDays(4), 'ends_at' => now()->subDays(4)->addHour(), 'status' => 'completed']);
        EventPlayerResult::query()->create(['occurrence_id' => $foreignOccurrence->id,
            'player_id' => $this->actor->playerId, 'score' => 0, 'recorded_at' => now()]);
        $this->attendance((string) $foreignOccurrence->id, 'present');
        $facts = app(EventPlayerIntelligenceQuery::class)->forPlayer($this->event, $this->actor);
        self::assertSame(2, $facts['resultCount']);
        self::assertSame('9223372036854775807', $facts['averageScore']);
        self::assertSame('9223372036854775807', $facts['bestScore']);
        self::assertSame(1, $facts['completed']);
        $empty = app(EventPlayerIntelligenceQuery::class)->forPlayer($this->event, app(ScenarioFactory::class)->unclaimedPlayer(62736));
        self::assertSame(0, $empty['resultCount']);
        self::assertNull($empty['averageScore']);
        self::assertNull($empty['reliabilityPercent']);
    }

    /** @return list<string> */
    private function history(int $count): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = ['id' => strtolower((string) Str::ulid()), 'event_id' => $this->event->id,
                'starts_at' => now()->subDays($i + 2), 'ends_at' => now()->subDays($i + 2)->addHour(), 'status' => 'completed'];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('event_occurrences')->insert($chunk);
        }

        return array_column($rows, 'id');
    }

    private function registration(string $occurrence, string $status): void
    {
        EventRegistration::query()->create(['occurrence_id' => $occurrence, 'player_id' => $this->actor->playerId, 'status' => $status,
            'registered_by_player_id' => $this->actor->playerId, 'registered_at' => now()]);
    }

    private function attendance(string $occurrence, string $status): void
    {
        EventAttendance::query()->create(['occurrence_id' => $occurrence, 'player_id' => $this->actor->playerId, 'status' => $status, 'recorded_by_player_id' => $this->actor->playerId, 'recorded_at' => now()]);
    }

    private function roster(string $occurrence, string $status): void
    {
        $id = strtolower((string) Str::ulid());
        DB::table('event_rosters')->insert(['id' => $id, 'occurrence_id' => $occurrence, 'key' => $id,
            'name' => 'History roster', 'roster_type' => 'roster', 'assignment_group' => 'history']);
        EventRosterMember::query()->create(['roster_id' => $id, 'player_id' => $this->actor->playerId,
            'status' => $status, 'assigned_by_player_id' => $this->actor->playerId, 'assigned_at' => now(),
            'responded_by_player_id' => in_array($status, ['confirmed', 'declined'], true) ? $this->actor->playerId : null,
            'responded_at' => in_array($status, ['confirmed', 'declined'], true) ? now() : null]);
    }

    private function rally(string $occurrence, string $status): void
    {
        $id = strtolower((string) Str::ulid());
        DB::table('rally_groups')->insert(['id' => $id, 'occurrence_id' => $occurrence, 'alliance_id' => $this->event->alliance_id, 'name' => 'History Rally']);
        RallyAssignment::query()->create(['rally_group_id' => $id, 'player_id' => $this->actor->playerId,
            'role' => 'joiner', 'status' => $status, 'assigned_by_player_id' => $this->actor->playerId, 'assigned_at' => now(),
            'responded_by_player_id' => in_array($status, ['confirmed', 'declined'], true) ? $this->actor->playerId : null,
            'responded_at' => in_array($status, ['confirmed', 'declined'], true) ? now() : null]);
    }
}
