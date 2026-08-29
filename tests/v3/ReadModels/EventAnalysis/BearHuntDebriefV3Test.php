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
use Illuminate\Auth\Access\AuthorizationException;
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

        $occurrence->load(['event.eventType.workflowDimensions', 'event.typeScope']);
        $debrief = app(BearHuntDebriefQuery::class)->forOccurrence($occurrence, $actor, false);
        self::assertTrue($debrief['signals']['acceptedResult']);
        self::assertTrue($debrief['signals']['newPersonalBest']);
        self::assertSame('accepted', $debrief['signals']['evidenceStatus']);
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
        $this->recordPlayerResult($previous, $actor, 100, 4);

        $otherAccount = $scenario->authUser();
        $otherOwner = $scenario->player((int) $otherAccount->id, 61104);
        $otherAlliance = $scenario->alliance($otherOwner);
        $scenario->roster($otherOwner, $otherAlliance);
        $wrongTarget = $this->bearHuntOccurrence($otherOwner, $otherAlliance, CarbonImmutable::now('UTC')->subDay());
        $wrongTarget->forceFill(['status' => EventOccurrenceStatus::Completed])->save();
        $this->recordPlayerResult($wrongTarget, $otherOwner, 999, 1);

        $current = $this->bearHuntOccurrence($actor, $alliance, CarbonImmutable::now('UTC'));
        $this->recordPlayerResult($current, $actor, 150, 2);

        $current->load(['event.eventType.workflowDimensions', 'event.typeScope']);
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

    public function test_zero_previous_damage_uses_absolute_delta_without_invalid_percentage(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61105);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);

        $previous = $this->bearHuntOccurrence($actor, $alliance, CarbonImmutable::now('UTC')->subDay());
        $previous->forceFill(['status' => EventOccurrenceStatus::Completed])->save();
        $this->recordPlayerResult($previous, $actor, 0, 2);

        $current = $this->bearHuntOccurrence($actor, $alliance, CarbonImmutable::now('UTC'));
        $this->recordPlayerResult($current, $actor, 50, 1);
        $current->load(['event.eventType.workflowDimensions', 'event.typeScope']);

        $debrief = app(BearHuntDebriefQuery::class)->forOccurrence($current, $actor, false);

        self::assertSame('previous_zero', $debrief['comparison']['allianceDamage']['state']);
        self::assertSame(50, $debrief['comparison']['allianceDamage']['delta']);
        self::assertNull($debrief['comparison']['allianceDamage']['percentChange']);
        self::assertSame(1, $debrief['comparison']['personalRank']['movement']);
    }

    public function test_idempotent_result_replay_does_not_change_debrief_totals_or_report_count(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61106);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $occurrence = $this->bearHuntOccurrence($actor, $alliance, CarbonImmutable::now('UTC'));
        $record = app(RecordBearHuntBattleReport::class);
        $sourceEvidenceId = (string) Str::ulid();
        $sourceCommitAttemptId = (string) Str::ulid();
        $idempotencyKey = hash('sha256', 'debrief-replay-idempotency');
        $fingerprint = hash('sha256', 'debrief-replay-report');
        $entries = [[
            'player_id' => $actor->playerId,
            'reported_rank' => 1,
            'damage_points' => 500,
        ]];

        $first = $record->handle(
            $actor->playerId,
            (string) $occurrence->id,
            $sourceEvidenceId,
            $sourceCommitAttemptId,
            $idempotencyKey,
            $fingerprint,
            '2026-08-23 01:30:00',
            $entries,
        );
        self::assertFalse($first->idempotentReplay);
        $before = app(BearHuntDebriefResultQuery::class)->forOccurrence((string) $occurrence->id);

        $replay = $record->handle(
            $actor->playerId,
            (string) $occurrence->id,
            (string) Str::ulid(),
            (string) Str::ulid(),
            $idempotencyKey,
            $fingerprint,
            '2026-08-23 01:30:00',
            $entries,
        );
        self::assertTrue($replay->idempotentReplay);
        $after = app(BearHuntDebriefResultQuery::class)->forOccurrence((string) $occurrence->id);

        self::assertSame($first->reportId, $replay->reportId);
        self::assertSame(500, $before['totalDamage']);
        self::assertSame(500, $after['totalDamage']);
        self::assertSame(1, $before['acceptedReportCount']);
        self::assertSame(1, $after['acceptedReportCount']);
    }

    public function test_history_is_bounded_to_twelve_runs(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61107);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $current = null;

        for ($daysAgo = 12; $daysAgo >= 0; $daysAgo--) {
            $run = $this->bearHuntOccurrence(
                $actor,
                $alliance,
                CarbonImmutable::now('UTC')->subDays($daysAgo),
            );
            $run->forceFill(['status' => EventOccurrenceStatus::Completed])->save();
            $this->recordPlayerResult($run, $actor, 100 + $daysAgo, 1);
            $current = $run;
        }
        self::assertInstanceOf(EventOccurrence::class, $current);
        $current->load(['event.eventType.workflowDimensions', 'event.typeScope']);

        $debrief = app(BearHuntDebriefQuery::class)->forOccurrence($current, $actor, false);

        self::assertCount(12, $debrief['runs']);
        self::assertCount(12, $debrief['personalTrend']);
        self::assertCount(12, $debrief['allianceTrend']);
        self::assertSame((string) $current->id, $debrief['runs'][0]['occurrenceId']);
    }

    public function test_history_composition_rejects_player_without_alliance_event_view_authority(): void
    {
        $scenario = new ScenarioFactory;
        $ownerAccount = $scenario->authUser();
        $owner = $scenario->player((int) $ownerAccount->id, 61108);
        $alliance = $scenario->alliance($owner);
        $scenario->roster($owner, $alliance);
        $occurrence = $this->bearHuntOccurrence($owner, $alliance, CarbonImmutable::now('UTC'));
        $occurrence->load(['event.eventType.workflowDimensions', 'event.typeScope']);

        $outsiderAccount = $scenario->authUser();
        $outsider = $scenario->player((int) $outsiderAccount->id, 61109);

        $this->expectException(AuthorizationException::class);
        app(BearHuntDebriefQuery::class)->forOccurrence($occurrence, $outsider, false);
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

    private function recordPlayerResult(
        EventOccurrence $occurrence,
        PlayerReference $player,
        int $score,
        int $rank,
    ): EventPlayerResult {
        return EventPlayerResult::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'player_id' => $player->playerId,
            'score' => $score,
            'rank' => $rank,
            'recorded_by_player_id' => $player->playerId,
            'recorded_at' => now(),
        ]);
    }
}
