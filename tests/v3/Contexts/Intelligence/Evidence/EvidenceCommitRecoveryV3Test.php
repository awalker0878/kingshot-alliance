<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Evidence\Actions\BeginEvidenceCommit;
use App\Contexts\Intelligence\Evidence\Actions\CommitReviewedBearHuntEvidence;
use App\Contexts\Intelligence\Evidence\Actions\FailEvidenceCommit;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReviewRow;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Results\Actions\RecordBearHuntBattleReport;
use App\Contexts\Operations\Results\Models\BearHuntBattleReport;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class EvidenceCommitRecoveryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_retry_after_operations_commit_and_lost_evidence_acknowledgement_reuses_destination_report(): void
    {
        [$actor, $allianceId, $occurrenceId] = $this->bearHunt();
        $review = $this->approvedReview($actor, $allianceId, $occurrenceId);
        $command = app(BeginEvidenceCommit::class)->handle($actor->playerId, (string) $review->id);
        $firstAttemptId = $command->commitAttemptId;

        $operationsReceipt = app(RecordBearHuntBattleReport::class)->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $command->occurrenceId,
            sourceEvidenceId: $command->evidenceId,
            sourceCommitAttemptId: $command->commitAttemptId,
            idempotencyKey: $command->idempotencyKey,
            reportFingerprint: $command->reportFingerprint,
            reportTimestampText: $command->reportTimestampText,
            entries: $command->entries,
        );
        self::assertFalse($operationsReceipt->idempotentReplay);
        self::assertSame(100, $this->score($occurrenceId, $actor->playerId));
        self::assertSame(1, BearHuntBattleReport::query()->where('occurrence_id', $occurrenceId)->count());

        app(FailEvidenceCommit::class)->handle(
            $actor->playerId,
            $firstAttemptId,
            new RuntimeException('Simulated acknowledgement loss after Operations commit.'),
        );
        self::assertSame(
            EvidenceCommitStatus::Failed,
            EvidenceCommitAttempt::query()->findOrFail($firstAttemptId)->status,
        );

        $retryReceipt = app(CommitReviewedBearHuntEvidence::class)->handle(
            $actor->playerId,
            (string) $review->id,
        );

        self::assertTrue($retryReceipt->idempotentReplay);
        self::assertSame($operationsReceipt->reportId, $retryReceipt->reportId);
        self::assertSame(100, $this->score($occurrenceId, $actor->playerId));
        self::assertSame(1, BearHuntBattleReport::query()->where('occurrence_id', $occurrenceId)->count());

        $attempts = EvidenceCommitAttempt::query()
            ->where('review_id', $review->id)
            ->orderBy('created_at')
            ->get();
        self::assertCount(2, $attempts);
        self::assertSame(EvidenceCommitStatus::Failed, $attempts[0]->status);
        self::assertSame(EvidenceCommitStatus::Succeeded, $attempts[1]->status);
        self::assertSame((string) $attempts[0]->idempotency_key, (string) $attempts[1]->idempotency_key);
        self::assertSame($operationsReceipt->reportId, (string) $attempts[1]->destination_report_id);
        self::assertSame(
            EvidenceLifecycleStatus::Committed,
            GameEvidence::query()->findOrFail($review->evidence_id)->lifecycle_status,
        );
    }

    /** @return array{0:PlayerReference,1:string,2:string} */
    private function bearHunt(): array
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 59114);
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

    private function approvedReview(
        PlayerReference $actor,
        string $allianceId,
        string $occurrenceId,
    ): EvidenceReview {
        $sha256 = hash('sha256', 'commit-recovery-source');
        $evidence = GameEvidence::query()->create([
            'alliance_id' => $allianceId,
            'occurrence_id' => $occurrenceId,
            'expected_kind' => EvidenceKind::BearHuntBattleReport,
            'kind' => EvidenceKind::BearHuntBattleReport,
            'lifecycle_status' => EvidenceLifecycleStatus::Approved,
            'original_name' => 'recovery.png',
            'disk' => 'local',
            'path' => 'evidence/recovery.png',
            'mime_type' => 'image/png',
            'size_bytes' => 100,
            'width' => 1080,
            'height' => 1920,
            'sha256' => $sha256,
            'uploaded_by_player_id' => $actor->playerId,
            'scanned_at' => now(),
        ]);
        $classification = EvidenceClassificationAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'status' => EvidenceAttemptStatus::Completed,
            'classifier_key' => 'recovery-fixture',
            'classifier_version' => '1',
            'input_sha256' => $sha256,
            'classified_kind' => EvidenceKind::BearHuntBattleReport,
            'confidence' => 0.99,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $extraction = EvidenceExtractionAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'classification_attempt_id' => $classification->id,
            'status' => EvidenceAttemptStatus::Completed,
            'extractor_key' => 'recovery-fixture',
            'extractor_version' => '1',
            'schema_version' => 'bear-hunt-report/1',
            'input_sha256' => $sha256,
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
            'report_timestamp_text' => '2026-08-22 13:05:23',
            'semantic_fingerprint' => hash('sha256', 'recovery-semantic-report'),
            'reviewed_by_player_id' => $actor->playerId,
            'reviewed_at' => now(),
        ]);
        EvidenceReviewRow::query()->create([
            'review_id' => $review->id,
            'row_ordinal' => 1,
            'player_id' => $actor->playerId,
            'player_name' => $actor->currentName,
            'reported_rank' => 1,
            'damage_points' => 100,
            'included' => true,
        ]);

        return $review;
    }

    private function score(string $occurrenceId, string $playerId): int
    {
        return (int) EventPlayerResult::query()
            ->where('occurrence_id', $occurrenceId)
            ->where('player_id', $playerId)
            ->value('score');
    }
}
