<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\EventAnalysis;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Participation\Models\EventAttendance;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\ReadModels\EventAnalysis\Queries\BearHuntDebriefQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class BearHuntDebriefReadModelV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_empty_run_keeps_every_unrecorded_fact_unavailable(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61600);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $occurrence = $this->occurrence($actor, $alliance, CarbonImmutable::now('UTC'));
        $occurrence->load(['event.eventType', 'event.typeScope.capabilities']);

        $debrief = app(BearHuntDebriefQuery::class)->forOccurrence($occurrence, $actor, false);

        self::assertFalse($debrief['summary']['resultsAvailable']);
        self::assertNull($debrief['summary']['totalDamage']);
        self::assertFalse($debrief['summary']['attendance']['available']);
        self::assertFalse($debrief['summary']['rallies']['available']);
        self::assertSame([], $debrief['governors']);
        self::assertNull($debrief['personal']['result']);
        self::assertNull($debrief['personal']['attendanceStatus']);
        self::assertNull($debrief['previousRun']);
        self::assertNull($debrief['comparison']);
    }

    public function test_active_governor_without_result_keeps_independent_attendance_truth(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61601);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $occurrence = $this->occurrence($actor, $alliance, CarbonImmutable::now('UTC'));
        EventAttendance::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'player_id' => $actor->playerId,
            'status' => EventAttendanceStatus::Present,
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => now(),
        ]);
        $occurrence->load(['event.eventType', 'event.typeScope.capabilities']);

        $debrief = app(BearHuntDebriefQuery::class)->forOccurrence($occurrence, $actor, false);

        self::assertFalse($debrief['summary']['resultsAvailable']);
        self::assertNull($debrief['summary']['totalDamage']);
        self::assertSame(0, $debrief['summary']['governorCount']);
        self::assertNull($debrief['personal']['result']);
        self::assertSame('present', $debrief['personal']['attendanceStatus']);
        self::assertTrue($debrief['summary']['attendance']['available']);
        self::assertSame(1, $debrief['summary']['attendance']['byStatus']['present']);
    }

    public function test_results_only_run_has_no_previous_hunt_or_inferred_participation(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61603);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $occurrence = $this->occurrence($actor, $alliance, CarbonImmutable::now('UTC'));
        $this->recordResult((string) $occurrence->id, $actor->playerId, $actor->playerId, 750, 1);
        $occurrence->load(['event.eventType', 'event.typeScope.capabilities']);

        $debrief = app(BearHuntDebriefQuery::class)->forOccurrence($occurrence, $actor, false);

        self::assertTrue($debrief['summary']['resultsAvailable']);
        self::assertSame(750, $debrief['summary']['totalDamage']);
        self::assertSame(1, $debrief['summary']['governorCount']);
        self::assertFalse($debrief['summary']['attendance']['available']);
        self::assertFalse($debrief['summary']['rallies']['available']);
        self::assertNull($debrief['governors'][0]['attendanceStatus']);
        self::assertFalse($debrief['governors'][0]['rallies']['available']);
        self::assertNull($debrief['previousRun']);
        self::assertNull($debrief['comparison']);
    }

    public function test_large_governor_list_does_not_add_per_governor_queries(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61602);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);

        $small = $this->occurrence($actor, $alliance, CarbonImmutable::now('UTC')->subDay());
        $small->forceFill(['status' => EventOccurrenceStatus::Completed])->save();
        $this->recordResult((string) $small->id, $actor->playerId, $actor->playerId, 100, 1);
        $small->load(['event.eventType', 'event.typeScope.capabilities']);

        DB::enableQueryLog();
        DB::flushQueryLog();
        app(BearHuntDebriefQuery::class)->forOccurrence($small, $actor, false);
        $smallQueryCount = count(DB::getQueryLog());

        $large = $this->occurrence($actor, $alliance, CarbonImmutable::now('UTC'));
        $this->recordResult((string) $large->id, $actor->playerId, $actor->playerId, 1000, 1);
        for ($index = 2; $index <= 100; $index++) {
            $player = Player::query()->create([
                'current_kingdom_id' => $actor->kingdomId,
                'game_player_id' => 'DEBRIEF-PERF-'.$index,
                'current_name' => 'Governor '.$index,
            ]);
            $this->recordResult(
                (string) $large->id,
                (string) $player->id,
                $actor->playerId,
                1000 - $index,
                $index,
            );
        }
        $large->load(['event.eventType', 'event.typeScope.capabilities']);

        DB::flushQueryLog();
        $debrief = app(BearHuntDebriefQuery::class)->forOccurrence($large, $actor, false);
        $largeQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertCount(100, $debrief['governors']);
        self::assertLessThanOrEqual(
            $smallQueryCount + 3,
            $largeQueryCount,
            'Bear Hunt Debrief must batch Governor reads instead of adding per-Governor queries.',
        );
    }

    private function occurrence(
        PlayerReference $actor,
        AllianceReference $alliance,
        CarbonImmutable $start,
    ): EventOccurrence {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: $start,
            title: 'Bear Hunt Read Model Fixture',
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);

        return EventOccurrence::query()->findOrFail($created->firstOccurrenceId);
    }

    private function recordResult(
        string $occurrenceId,
        string $playerId,
        string $recordedByPlayerId,
        int $score,
        int $rank,
    ): void {
        EventPlayerResult::query()->create([
            'occurrence_id' => $occurrenceId,
            'player_id' => $playerId,
            'score' => $score,
            'rank' => $rank,
            'recorded_by_player_id' => $recordedByPlayerId,
            'recorded_at' => now(),
        ]);
    }
}
