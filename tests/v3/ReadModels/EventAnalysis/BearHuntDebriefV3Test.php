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
use App\Contexts\Operations\Participation\Queries\BearHuntAttendanceSummaryQuery;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Rallies\Models\RallyGroup;
use App\Contexts\Operations\Rallies\Queries\RallyParticipationSummaryQuery;
use App\Contexts\Operations\Results\Actions\RecordBearHuntBattleReport;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\Contexts\Operations\Results\Queries\BearHuntDebriefResultQuery;
use App\ReadModels\EventAnalysis\Queries\BearHuntDebriefQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class BearHuntDebriefV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_current_run_composes_authoritative_damage_attendance_and_recorded_rallies(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61101);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $other = $scenario->unclaimedPlayer(61101);
        $scenario->roster($actor, $alliance, $other);
        $occurrence = $this->bearHuntOccurrence($actor, $alliance, CarbonImmutable::now('UTC')->addHour());

        $receipt = app(RecordBearHuntBattleReport::class)->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: (string) $occurrence->id,
            sourceEvidenceId: (string) Str::ulid(),
            sourceCommitAttemptId: (string) Str::ulid(),
            idempotencyKey: hash('sha256', 'debrief-current-idempotency'),
            reportFingerprint: hash('sha256', 'debrief-current-report'),
            reportTimestampText: '2026-08-23 01:00:00',
            entries: [
                [
                    'player_id' => $actor->playerId,
                    'reported_rank' => 2,
                    'damage_points' => 100,
                ],
                [
                    'player_id' => $other->playerId,
                    'reported_rank' => 1,
                    'damage_points' => 200,
                ],
            ],
        );
        self::assertFalse($receipt->idempotentReplay);

        EventAttendance::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'player_id' => $actor->playerId,
            'status' => EventAttendanceStatus::Present,
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => now(),
        ]);
        EventAttendance::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'player_id' => $other->playerId,
            'status' => EventAttendanceStatus::Absent,
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => now(),
        ]);

        $group = RallyGroup::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'alliance_id' => $alliance->allianceId,
            'name' => 'Bear Rally 1',
            'sort_order' => 0,
            'created_by_player_id' => $actor->playerId,
        ]);
        RallyAssignment::query()->create([
            'rally_group_id' => (string) $group->id,
            'player_id' => $actor->playerId,
            'role' => RallyAssignmentRole::Lead,
            'status' => RallyAssignmentStatus::Participated,
            'assigned_by_player_id' => $actor->playerId,
            'assigned_at' => now(),
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => now(),
        ]);
        RallyAssignment::query()->create([
            'rally_group_id' => (string) $group->id,
            'player_id' => $other->playerId,
            'role' => RallyAssignmentRole::Joiner,
            'status' => RallyAssignmentStatus::Confirmed,
            'assigned_by_player_id' => $actor->playerId,
            'assigned_at' => now(),
        ]);

        $results = app(BearHuntDebriefResultQuery::class)->forOccurrence((string) $occurrence->id);
        self::assertTrue($results['available']);
        self::assertSame(300, $results['totalDamage']);
        self::assertSame(2, $results['governorCount']);
        self::assertSame(1, $results['acceptedReportCount']);
        self::assertSame(200, $results['governors'][0]['damage']);
        self::assertSame(1, $results['governors'][0]['rank']);
        self::assertSame(100, $results['governors'][1]['damage']);
        self::assertSame(2, $results['governors'][1]['rank']);

        $attendance = app(BearHuntAttendanceSummaryQuery::class)->forOccurrence((string) $occurrence->id);
        self::assertTrue($attendance['available']);
        self::assertSame(1, $attendance['byStatus']['present']);
        self::assertSame(1, $attendance['byStatus']['absent']);
        self::assertSame(50.0, $attendance['ratePercent']);

        $rallies = app(RallyParticipationSummaryQuery::class)->forOccurrence((string) $occurrence->id);
        self::assertTrue($rallies['available']);
        self::assertSame(1, $rallies['recordedAssignments']);
        self::assertSame(1, $rallies['participated']);
        self::assertSame(1, $rallies['led']);
        self::assertSame(0, $rallies['joined']);
        self::assertArrayNotHasKey($other->playerId, $rallies['players']);
    }

    public function test_recorded_absence_means_rally_data_is_available_with_zero_participation(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61102);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $occurrence = $this->bearHuntOccurrence($actor, $alliance, CarbonImmutable::now('UTC')->addHour());

        $group = RallyGroup::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'alliance_id' => $alliance->allianceId,
            'name' => 'Bear Rally 1',
            'sort_order' => 0,
            'created_by_player_id' => $actor->playerId,
        ]);
        RallyAssignment::query()->create([
            'rally_group_id' => (string) $group->id,
            'player_id' => $actor->playerId,
            'role' => RallyAssignmentRole::Joiner,
            'status' => RallyAssignmentStatus::Absent,
            'assigned_by_player_id' => $actor->playerId,
            'assigned_at' => now(),
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => now(),
        ]);

        $rallies = app(RallyParticipationSummaryQuery::class)->forOccurrence((string) $occurrence->id);
        self::assertTrue($rallies['available']);
        self::assertSame(0, $rallies['participated']);
        self::assertSame(0, $rallies['players'][$actor->playerId]['participated']);

        RallyAssignment::query()->where('player_id', $actor->playerId)->update([
            'status' => RallyAssignmentStatus::Confirmed->value,
            'recorded_at' => null,
            'recorded_by_player_id' => null,
        ]);
        $unrecorded = app(RallyParticipationSummaryQuery::class)->forOccurrence((string) $occurrence->id);
        self::assertFalse($unrecorded['available']);
        self::assertSame(0, $unrecorded['participated']);
    }

    public function test_previous_run_uses_same_alliance_bear_hunt_and_preserves_missing_data(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61103);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);

        $previous = $this->bearHuntOccurrence($actor, $alliance, CarbonImmutable::now('UTC')->subDays(2));
        $previous->forceFill(['status' => EventOccurrenceStatus::Completed])->save();
        EventPlayerResult::query()->create([
            'occurrence_id' => (string) $previous->id,
            'player_id' => $actor->playerId,
            'score' => 100,
            'rank' => 4,
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => now(),
        ]);

        $otherAlliance = $scenario->alliance($actor);
        $scenario->roster($actor, $otherAlliance);
        $wrongTarget = $this->bearHuntOccurrence($actor, $otherAlliance, CarbonImmutable::now('UTC')->subDay());
        $wrongTarget->forceFill(['status' => EventOccurrenceStatus::Completed])->save();
        EventPlayerResult::query()->create([
            'occurrence_id' => (string) $wrongTarget->id,
            'player_id' => $actor->playerId,
            'score' => 999,
            'rank' => 1,
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => now(),
        ]);

        $current = $this->bearHuntOccurrence($actor, $alliance, CarbonImmutable::now('UTC'));
        EventPlayerResult::query()->create([
            'occurrence_id' => (string) $current->id,
            'player_id' => $actor->playerId,
            'score' => 150,
            'rank' => 2,
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => now(),
        ]);

        $current->load(['event.eventType', 'event.typeScope.capabilities']);
        $debrief = app(BearHuntDebriefQuery::class)->forOccurrence($current, $actor, false);

        self::assertSame((string) $previous->id, $debrief['previousRun']['occurrenceId']);
        self::assertSame(50, $debrief['comparison']['allianceDamage']['delta']);
        self::assertSame(50.0, $debrief['comparison']['allianceDamage']['percentChange']);
        self::assertSame('unavailable', $debrief['comparison']['recordedRallies']['state']);
        self::assertSame([], $debrief['unmatchedGovernors']);
        self::assertFalse($debrief['canReviewEvidence']);
        self::assertCount(2, $debrief['runs']);
        self::assertSame(150, $debrief['personalTrend'][1]['damage']);
    }

    private function bearHuntOccurrence(
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
            title: 'Bear Hunt Debrief Fixture',
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);

        return EventOccurrence::query()->findOrFail($created->firstOccurrenceId);
    }
}
