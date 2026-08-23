<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Evidence\Actions\EnforceEvidenceRetention;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class EvidenceRetentionV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_committed_retention_removes_binary_but_preserves_commit_provenance_tombstone(): void
    {
        Storage::fake('local');
        config()->set('evidence.disk', 'local');
        config()->set('evidence.retention.committed_binary_days', 1);
        CarbonImmutable::setTestNow('2026-08-23 01:00:00 UTC');
        [$actor, $allianceId, $occurrenceId] = $this->bearHunt();
        Storage::disk('local')->put('evidence/retention/committed.png', 'committed-private-image');
        $evidence = $this->evidence(
            actor: $actor,
            allianceId: $allianceId,
            occurrenceId: $occurrenceId,
            status: EvidenceLifecycleStatus::Committed,
            path: 'evidence/retention/committed.png',
            source: 'committed-private-image',
        );
        $evidence->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();
        [$reviewId] = $this->review($evidence, $actor, $allianceId, $occurrenceId);
        EvidenceCommitAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'review_id' => $reviewId,
            'status' => EvidenceCommitStatus::Succeeded,
            'idempotency_key' => hash('sha256', 'retention-commit'),
            'destination_context' => 'operations.results',
            'destination_report_id' => null,
            'destination_receipt' => ['report_id' => 'retained-domain-report'],
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ]);

        self::assertSame(1, app(EnforceEvidenceRetention::class)->handle());

        $evidence->refresh();
        self::assertSame(EvidenceLifecycleStatus::Committed, $evidence->lifecycle_status);
        self::assertNull($evidence->path);
        self::assertSame('[redacted]', $evidence->original_name);
        self::assertNotNull($evidence->binary_deleted_at);
        self::assertSame('retention_committed_binary', $evidence->deletion_reason);
        Storage::disk('local')->assertMissing('evidence/retention/committed.png');
        self::assertTrue(EvidenceReview::query()->whereKey($reviewId)->exists());
        self::assertTrue(EvidenceCommitAttempt::query()->where('evidence_id', $evidence->id)->where('status', EvidenceCommitStatus::Succeeded->value)->exists());
    }

    public function test_expired_uncommitted_evidence_is_redacted_and_purged(): void
    {
        Storage::fake('local');
        config()->set('evidence.disk', 'local');
        config()->set('evidence.retention.failed_days', 1);
        CarbonImmutable::setTestNow('2026-08-23 01:00:00 UTC');
        [$actor, $allianceId, $occurrenceId] = $this->bearHunt();
        Storage::disk('local')->put('evidence/retention/failed.png', 'failed-private-image');
        $evidence = $this->evidence(
            actor: $actor,
            allianceId: $allianceId,
            occurrenceId: $occurrenceId,
            status: EvidenceLifecycleStatus::Failed,
            path: 'evidence/retention/failed.png',
            source: 'failed-private-image',
        );
        $evidenceId = (string) $evidence->id;
        $evidence->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();

        self::assertSame(1, app(EnforceEvidenceRetention::class)->handle());

        self::assertFalse(GameEvidence::query()->whereKey($evidenceId)->exists());
        Storage::disk('local')->assertMissing('evidence/retention/failed.png');
    }

    /** @return array{0:PlayerReference,1:string,2:string} */
    private function bearHunt(): array
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 59113);
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
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);

        return [$actor, $alliance->allianceId, $created->firstOccurrenceId];
    }

    private function evidence(
        PlayerReference $actor,
        string $allianceId,
        string $occurrenceId,
        EvidenceLifecycleStatus $status,
        string $path,
        string $source,
    ): GameEvidence {
        return GameEvidence::query()->create([
            'alliance_id' => $allianceId,
            'occurrence_id' => $occurrenceId,
            'expected_kind' => EvidenceKind::BearHuntBattleReport,
            'kind' => EvidenceKind::BearHuntBattleReport,
            'lifecycle_status' => $status,
            'original_name' => 'battle-report.png',
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'image/png',
            'size_bytes' => strlen($source),
            'width' => 1080,
            'height' => 1920,
            'sha256' => hash('sha256', $source),
            'uploaded_by_player_id' => $actor->playerId,
            'scanned_at' => now(),
        ]);
    }

    /** @return array{0:string,1:string} */
    private function review(
        GameEvidence $evidence,
        PlayerReference $actor,
        string $allianceId,
        string $occurrenceId,
    ): array {
        $classification = EvidenceClassificationAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'status' => EvidenceAttemptStatus::Completed,
            'classifier_key' => 'retention-fixture',
            'classifier_version' => '1',
            'input_sha256' => $evidence->sha256,
            'classified_kind' => EvidenceKind::BearHuntBattleReport,
            'confidence' => 0.99,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $extraction = EvidenceExtractionAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'classification_attempt_id' => $classification->id,
            'status' => EvidenceAttemptStatus::Completed,
            'extractor_key' => 'retention-fixture',
            'extractor_version' => '1',
            'schema_version' => 'bear-hunt-report/1',
            'input_sha256' => $evidence->sha256,
            'overall_confidence' => 0.99,
            'field_count' => 0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $review = EvidenceReview::query()->create([
            'evidence_id' => $evidence->id,
            'extraction_attempt_id' => $extraction->id,
            'alliance_id' => $allianceId,
            'occurrence_id' => $occurrenceId,
            'revision_number' => 1,
            'status' => EvidenceReviewStatus::Approved,
            'semantic_fingerprint' => hash('sha256', 'retention-review'),
            'reviewed_by_player_id' => $actor->playerId,
            'reviewed_at' => now(),
        ]);

        return [(string) $review->id, (string) $extraction->id];
    }
}
