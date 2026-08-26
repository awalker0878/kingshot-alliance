<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Queries\EventEvidenceCommandQuery;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Results\Models\EventResult;
use App\ReadModels\EventManagement\Queries\EventCommandQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class EventEvidenceCommandQueryV3Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_evidence_bound_reports_incomplete_coverage_and_blocks_event_closeout(): void
    {
        $now = CarbonImmutable::parse('2026-08-25 18:00:00', 'UTC');
        CarbonImmutable::setTestNow($now);

        $scenario = new ScenarioFactory;
        $user = $scenario->authUser();
        $actor = $scenario->player((int) $user->id, 76201);
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
            firstLocalStart: $now->subHours(2),
            title: 'Evidence Coverage Test',
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);

        $occurrence = EventOccurrence::query()->findOrFail($created->firstOccurrenceId);
        $occurrence->forceFill(['status' => EventOccurrenceStatus::Completed])->save();
        EventResult::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'outcome' => 'recorded',
            'score' => 0,
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => $now,
        ]);

        foreach (range(1, 201) as $index) {
            GameEvidence::query()->create([
                'alliance_id' => $alliance->allianceId,
                'occurrence_id' => (string) $occurrence->id,
                'expected_kind' => EvidenceKind::BearHuntBattleReport,
                'kind' => EvidenceKind::BearHuntBattleReport,
                'lifecycle_status' => EvidenceLifecycleStatus::Committed,
                'original_name' => "battle-report-{$index}.png",
                'disk' => 'local',
                'path' => "evidence/test/{$index}.png",
                'mime_type' => 'image/png',
                'size_bytes' => 19,
                'width' => 1080,
                'height' => 1920,
                'sha256' => hash('sha256', "event-command-evidence-{$index}"),
                'uploaded_by_player_id' => $actor->playerId,
                'scanned_at' => $now,
            ]);
        }

        $summary = app(EventEvidenceCommandQuery::class)->forBearHuntOccurrence(
            $actor->playerId,
            (string) $occurrence->id,
        );
        self::assertFalse($summary['coverageComplete']);
        self::assertSame(200, $summary['evidenceCount']);

        $event = Event::query()
            ->with(['eventType', 'typeScope.capabilities', 'occurrences'])
            ->findOrFail($created->eventId);
        $projection = app(EventCommandQuery::class)->forEvent(
            $actor,
            $event,
            (string) $occurrence->id,
        );

        self::assertSame('closeout_required', $projection['state']);
        $evidenceSection = collect($projection['sections'])->firstWhere('key', 'evidence');
        self::assertIsArray($evidenceSection);
        $coverageItem = collect($evidenceSection['items'])->firstWhere(
            'code',
            'closeout.evidence_coverage_unknown',
        );
        self::assertIsArray($coverageItem);
        self::assertSame('unknown', $coverageItem['status']);
        self::assertSame('blocking', $coverageItem['severity']);
        self::assertSame('intelligence.evidence', $coverageItem['owner']);
        self::assertSame('evidence', $coverageItem['classification']);
        self::assertSame(
            'events.command.items.evidenceCoverageIncomplete',
            $coverageItem['messageKey'],
        );
        self::assertIsArray($coverageItem['handoff']);
        self::assertStringContainsString('/screenshot-intake', $coverageItem['handoff']['href']);
    }
}
