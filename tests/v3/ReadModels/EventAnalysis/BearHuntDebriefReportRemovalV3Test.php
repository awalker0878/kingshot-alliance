<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\EventAnalysis;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Results\Actions\RecordBearHuntBattleReport;
use App\Contexts\Operations\Results\Actions\RemoveBearHuntBattleReport;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\ReadModels\EventAnalysis\Queries\BearHuntDebriefQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class BearHuntDebriefReportRemovalV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_removed_report_recomputes_debrief_back_to_the_preserved_pre_import_baseline(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61701);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $occurrence = $this->occurrence($actor, $alliance);

        EventPlayerResult::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'player_id' => $actor->playerId,
            'score' => 75,
            'rank' => 1,
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => now(),
        ]);

        $receipt = app(RecordBearHuntBattleReport::class)->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: (string) $occurrence->id,
            sourceEvidenceId: (string) Str::ulid(),
            sourceCommitAttemptId: (string) Str::ulid(),
            idempotencyKey: hash('sha256', 'debrief-removal-idempotency'),
            reportFingerprint: hash('sha256', 'debrief-removal-report'),
            reportTimestampText: '2026-08-23 02:00:00',
            entries: [[
                'player_id' => $actor->playerId,
                'reported_rank' => 1,
                'damage_points' => 500,
            ]],
        );

        $occurrence->load(['event.eventType', 'event.typeScope.capabilities']);
        $withReport = app(BearHuntDebriefQuery::class)->forOccurrence($occurrence, $actor, false);
        self::assertSame(575, $withReport['summary']['totalDamage']);
        self::assertSame(1, $withReport['summary']['acceptedReportCount']);
        self::assertSame(575, $withReport['personal']['result']['damage']);

        app(RemoveBearHuntBattleReport::class)->handle(
            $actor->playerId,
            $receipt->reportId,
            'Remove the imported report because the source was entered in error.',
        );

        $afterRemoval = app(BearHuntDebriefQuery::class)->forOccurrence($occurrence, $actor, false);
        self::assertTrue($afterRemoval['summary']['resultsAvailable']);
        self::assertSame(75, $afterRemoval['summary']['totalDamage']);
        self::assertSame(0, $afterRemoval['summary']['acceptedReportCount']);
        self::assertSame(1, $afterRemoval['summary']['governorCount']);
        self::assertSame(75, $afterRemoval['personal']['result']['damage']);
        self::assertSame(1, $afterRemoval['personal']['result']['rank']);
    }

    private function occurrence(PlayerReference $actor, AllianceReference $alliance): EventOccurrence
    {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC'),
            title: 'Bear Hunt Removal Fixture',
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);

        return EventOccurrence::query()->findOrFail($created->firstOccurrenceId);
    }
}
