<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\EventAnalysis;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Participation\Models\EventAttendance;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Rallies\Models\RallyGroup;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\ReadModels\EventAnalysis\Queries\BearHuntDebriefQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class BearHuntDebriefComparisonV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_previous_hunt_compares_alliance_and_personal_recorded_facts(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61201);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $other = $scenario->unclaimedPlayer(61201);
        $scenario->roster($actor, $alliance, $other);

        $previous = $this->occurrence($actor, $alliance, CarbonImmutable::now('UTC')->subDay());
        $previous->forceFill(['status' => EventOccurrenceStatus::Completed])->save();
        $this->recordPlayerResult($previous, $actor, 100, 1);
        $this->attendance($previous, $actor, EventAttendanceStatus::Absent, $actor);
        $this->attendance($previous, $other, EventAttendanceStatus::Present, $actor);
        $this->rally($previous, $alliance, $actor, $actor, 'Previous Rally');

        $current = $this->occurrence($actor, $alliance, CarbonImmutable::now('UTC'));
        $this->recordPlayerResult($current, $actor, 150, 1);
        $this->recordPlayerResult($current, $other, 75, 2);
        $this->attendance($current, $actor, EventAttendanceStatus::Present, $actor);
        $this->attendance($current, $other, EventAttendanceStatus::Present, $actor);
        $this->rally($current, $alliance, $actor, $actor, 'Current Rally 1');
        $this->rally($current, $alliance, $actor, $actor, 'Current Rally 2');
        $current->load(['event.eventType.workflowDimensions', 'event.typeScope']);

        $debrief = app(BearHuntDebriefQuery::class)->forOccurrence($current, $actor, false);
        $comparison = $debrief['comparison'];
        self::assertIsArray($comparison);

        self::assertSame(125, $comparison['allianceDamage']['delta']);
        self::assertSame(1, $comparison['governorCount']['delta']);
        self::assertSame(1, $comparison['attendancePresent']['delta']);
        self::assertSame(50.0, $comparison['attendanceRate']['delta']);
        self::assertSame(1, $comparison['recordedRallies']['delta']);
        self::assertSame(50, $comparison['personalDamage']['delta']);
        self::assertSame('present', $comparison['personalAttendance']['current']);
        self::assertSame('absent', $comparison['personalAttendance']['previous']);
        self::assertSame('available', $comparison['personalAttendance']['state']);
        self::assertSame(1, $comparison['personalRallies']['delta']);
        self::assertSame('increased', $debrief['signals']['allianceDamage']);
        self::assertSame('increased', $debrief['signals']['personalDamage']);
        self::assertSame('increased', $debrief['signals']['personalRallies']);
        self::assertFalse($debrief['signals']['acceptedResult']);
        self::assertFalse($debrief['signals']['newPersonalBest']);
        self::assertSame('recorded_without_accepted_evidence', $debrief['signals']['evidenceStatus']);
    }

    public function test_personal_comparisons_remain_unavailable_when_facts_were_not_recorded(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61202);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);

        $previous = $this->occurrence($actor, $alliance, CarbonImmutable::now('UTC')->subDay());
        $previous->forceFill(['status' => EventOccurrenceStatus::Completed])->save();
        $this->recordPlayerResult($previous, $actor, 100, 1);

        $current = $this->occurrence($actor, $alliance, CarbonImmutable::now('UTC'));
        $this->recordPlayerResult($current, $actor, 120, 1);
        $current->load(['event.eventType.workflowDimensions', 'event.typeScope']);

        $debrief = app(BearHuntDebriefQuery::class)->forOccurrence($current, $actor, false);
        $comparison = $debrief['comparison'];
        self::assertIsArray($comparison);

        self::assertSame('unavailable', $comparison['attendancePresent']['state']);
        self::assertSame('unavailable', $comparison['attendanceRate']['state']);
        self::assertSame('unavailable', $comparison['recordedRallies']['state']);
        self::assertSame('unavailable', $comparison['personalAttendance']['state']);
        self::assertSame('unavailable', $comparison['personalRallies']['state']);
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
            title: 'Bear Hunt Comparison Fixture',
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);

        return EventOccurrence::query()->findOrFail($created->firstOccurrenceId);
    }

    private function recordPlayerResult(
        EventOccurrence $occurrence,
        PlayerReference $player,
        int $score,
        int $rank,
    ): void {
        EventPlayerResult::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'player_id' => $player->playerId,
            'score' => $score,
            'rank' => $rank,
            'recorded_by_player_id' => $player->playerId,
            'recorded_at' => now(),
        ]);
    }

    private function attendance(
        EventOccurrence $occurrence,
        PlayerReference $player,
        EventAttendanceStatus $status,
        PlayerReference $actor,
    ): void {
        EventAttendance::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'player_id' => $player->playerId,
            'status' => $status,
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => now(),
        ]);
    }

    private function rally(
        EventOccurrence $occurrence,
        AllianceReference $alliance,
        PlayerReference $player,
        PlayerReference $actor,
        string $name,
    ): void {
        $group = RallyGroup::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'alliance_id' => $alliance->allianceId,
            'name' => $name,
            'sort_order' => 0,
            'created_by_player_id' => $actor->playerId,
        ]);
        RallyAssignment::query()->create([
            'rally_group_id' => (string) $group->id,
            'player_id' => $player->playerId,
            'role' => RallyAssignmentRole::Lead,
            'status' => RallyAssignmentStatus::Participated,
            'assigned_by_player_id' => $actor->playerId,
            'assigned_at' => now(),
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => now(),
        ]);
    }
}
