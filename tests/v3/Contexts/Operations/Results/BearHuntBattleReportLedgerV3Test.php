<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\Results;

use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Results\Actions\RecordBearHuntBattleReport;
use App\Contexts\Operations\Results\Actions\RemoveBearHuntBattleReport;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class BearHuntBattleReportLedgerV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_reports_are_idempotent_aggregate_and_recompute_after_removal(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 59101);
        $alliance = $scenario->alliance($actor);
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            title: 'Screenshot Intake Bear Hunt',
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);
        $occurrenceId = $created->firstOccurrenceId;
        $record = app(RecordBearHuntBattleReport::class);

        $first = $record->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrenceId,
            sourceEvidenceId: (string) Str::ulid(),
            sourceCommitAttemptId: (string) Str::ulid(),
            idempotencyKey: hash('sha256', 'first-idempotency'),
            reportFingerprint: hash('sha256', 'first-report'),
            reportTimestampText: '2026-08-22 13:05:23',
            entries: [[
                'player_id' => $actor->playerId,
                'reported_rank' => 1,
                'damage_points' => 100,
            ]],
        );
        self::assertFalse($first->replayed);
        self::assertSame(100, $this->score($occurrenceId, $actor->playerId));

        $replay = $record->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrenceId,
            sourceEvidenceId: (string) Str::ulid(),
            sourceCommitAttemptId: (string) Str::ulid(),
            idempotencyKey: hash('sha256', 'first-idempotency'),
            reportFingerprint: hash('sha256', 'first-report'),
            reportTimestampText: '2026-08-22 13:05:23',
            entries: [[
                'player_id' => $actor->playerId,
                'reported_rank' => 1,
                'damage_points' => 100,
            ]],
        );
        self::assertTrue($replay->replayed);
        self::assertSame($first->reportId, $replay->reportId);
        self::assertSame(100, $this->score($occurrenceId, $actor->playerId));

        $second = $record->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrenceId,
            sourceEvidenceId: (string) Str::ulid(),
            sourceCommitAttemptId: (string) Str::ulid(),
            idempotencyKey: hash('sha256', 'second-idempotency'),
            reportFingerprint: hash('sha256', 'second-report'),
            reportTimestampText: '2026-08-22 13:06:23',
            entries: [[
                'player_id' => $actor->playerId,
                'reported_rank' => 1,
                'damage_points' => 200,
            ]],
        );
        self::assertSame(300, $this->score($occurrenceId, $actor->playerId));

        app(RemoveBearHuntBattleReport::class)->handle(
            $actor->playerId,
            $second->reportId,
            'The reviewer confirmed this screenshot represented the wrong rally.',
        );
        self::assertSame(100, $this->score($occurrenceId, $actor->playerId));
    }

    private function score(string $occurrenceId, string $playerId): int
    {
        return (int) EventPlayerResult::query()
            ->where('occurrence_id', $occurrenceId)
            ->where('player_id', $playerId)
            ->value('score');
    }
}
