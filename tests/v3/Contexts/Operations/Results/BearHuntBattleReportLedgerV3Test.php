<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\Results;

use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Results\Actions\RecordBearHuntBattleReport;
use App\Contexts\Operations\Results\Actions\RemoveBearHuntBattleReport;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
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
        $scenario->roster($actor, $alliance);
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
        self::assertFalse($first->idempotentReplay);
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
        self::assertTrue($replay->idempotentReplay);
        self::assertSame($first->reportId, $replay->reportId);
        self::assertSame(100, $this->score($occurrenceId, $actor->playerId));
        self::assertTrue(AuditEvent::query()->where('event', 'bear_hunt.battle_report_replayed')->where('subject_id', $first->reportId)->exists());
        self::assertTrue(OutboxMessage::query()->where('event_type', 'bear_hunt.battle_report_replayed')->where('aggregate_id', $first->reportId)->exists());

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

    public function test_import_preserves_and_restores_the_preexisting_result_baseline(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 59102);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
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
            title: 'Baseline Preservation Bear Hunt',
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);
        $occurrenceId = $created->firstOccurrenceId;

        $existing = EventPlayerResult::query()->create([
            'occurrence_id' => $occurrenceId,
            'player_id' => $actor->playerId,
            'score' => 50,
            'rank' => 7,
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => now(),
        ]);

        $report = app(RecordBearHuntBattleReport::class)->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrenceId,
            sourceEvidenceId: (string) Str::ulid(),
            sourceCommitAttemptId: (string) Str::ulid(),
            idempotencyKey: hash('sha256', 'baseline-idempotency'),
            reportFingerprint: hash('sha256', 'baseline-report'),
            reportTimestampText: '2026-08-22 14:05:23',
            entries: [[
                'player_id' => $actor->playerId,
                'reported_rank' => 1,
                'damage_points' => 100,
            ]],
        );

        $existing->refresh();
        self::assertSame(150, $existing->score);

        app(RemoveBearHuntBattleReport::class)->handle(
            $actor->playerId,
            $report->reportId,
            'The imported report was confirmed to be unrelated to this occurrence.',
        );

        $existing->refresh();
        self::assertSame(50, $existing->score);
        self::assertSame(7, $existing->rank);
    }

    private function score(string $occurrenceId, string $playerId): int
    {
        return (int) EventPlayerResult::query()
            ->where('occurrence_id', $occurrenceId)
            ->where('player_id', $playerId)
            ->value('score');
    }
}
