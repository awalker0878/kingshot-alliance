<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\Intelligence\Evidence\Actions\DeleteEvidence;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class EvidenceLifecycleV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_evidence_redacts_source_without_deleting_domain_result(): void
    {
        Storage::fake('local');
        config()->set('evidence.disk', 'local');
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 59102);
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
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);
        $occurrenceId = $created->firstOccurrenceId;
        Storage::disk('local')->put('evidence/test/source.png', 'private-image-bytes');

        $evidence = GameEvidence::query()->create([
            'alliance_id' => $alliance->allianceId,
            'occurrence_id' => $occurrenceId,
            'expected_kind' => EvidenceKind::BearHuntBattleReport,
            'kind' => EvidenceKind::BearHuntBattleReport,
            'lifecycle_status' => EvidenceLifecycleStatus::Committed,
            'original_name' => 'battle-report.png',
            'disk' => 'local',
            'path' => 'evidence/test/source.png',
            'mime_type' => 'image/png',
            'size_bytes' => 19,
            'width' => 1080,
            'height' => 1920,
            'sha256' => hash('sha256', 'private-image-bytes'),
            'uploaded_by_player_id' => $actor->playerId,
            'scanned_at' => now(),
        ]);
        $result = EventPlayerResult::query()->create([
            'occurrence_id' => $occurrenceId,
            'player_id' => $actor->playerId,
            'score' => 123456,
            'rank' => 1,
            'recorded_by_player_id' => $actor->playerId,
            'recorded_at' => now(),
        ]);

        app(DeleteEvidence::class)->handle($actor->playerId, $occurrenceId, (string) $evidence->id);

        $evidence->refresh();
        self::assertSame(EvidenceLifecycleStatus::Deleted, $evidence->lifecycle_status);
        self::assertNull($evidence->path);
        self::assertSame('[redacted]', $evidence->original_name);
        self::assertNotNull($evidence->binary_deleted_at);
        Storage::disk('local')->assertMissing('evidence/test/source.png');
        self::assertSame(123456, (int) EventPlayerResult::query()->findOrFail($result->id)->score);
    }
}
