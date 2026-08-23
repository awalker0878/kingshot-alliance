<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Evidence\Actions\BeginEvidenceCommit;
use App\Contexts\Intelligence\Evidence\Actions\ResolveSemanticDuplicate;
use App\Contexts\Intelligence\Evidence\Actions\SaveEvidenceReview;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class EvidenceDuplicateV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_semantic_duplicate_blocks_commit_until_manager_explicitly_marks_report_distinct(): void
    {
        [$actor, $allianceId, $occurrenceId] = $this->bearHunt();
        [$firstEvidence, $firstExtractionId] = $this->evidence($actor, $allianceId, $occurrenceId, 'first');
        [$secondEvidence, $secondExtractionId] = $this->evidence($actor, $allianceId, $occurrenceId, 'second');
        $rows = $this->rows($actor);
        $save = app(SaveEvidenceReview::class);

        $firstReviewId = $save->handle(
            $actor->playerId,
            $occurrenceId,
            (string) $firstEvidence->id,
            $firstExtractionId,
            $rows,
            '2026-08-22 13:05:23',
        );
        $secondReviewId = $save->handle(
            $actor->playerId,
            $occurrenceId,
            (string) $secondEvidence->id,
            $secondExtractionId,
            $rows,
            '2026-08-22 13:05:23',
        );

        $firstReview = EvidenceReview::query()->findOrFail($firstReviewId);
        $secondReview = EvidenceReview::query()->findOrFail($secondReviewId);
        self::assertSame(EvidenceReviewStatus::Approved, $firstReview->status);
        self::assertSame(EvidenceReviewStatus::DuplicateBlocked, $secondReview->status);
        self::assertSame((string) $firstReview->id, (string) $secondReview->semantic_duplicate_review_id);
        self::assertSame(
            EvidenceLifecycleStatus::NeedsReview,
            GameEvidence::query()->findOrFail($secondEvidence->id)->lifecycle_status,
        );

        try {
            app(BeginEvidenceCommit::class)->handle($actor->playerId, $occurrenceId, $secondReviewId);
            self::fail('Expected semantic duplicate to block commit.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('review', $exception->errors());
        }

        $justification = 'This is a separate rally report with matching visible totals.';
        app(ResolveSemanticDuplicate::class)->handle(
            $actor->playerId,
            $occurrenceId,
            $secondReviewId,
            $justification,
        );
        $secondReview->refresh();
        self::assertSame(EvidenceReviewStatus::Approved, $secondReview->status);
        self::assertSame($justification, $secondReview->duplicate_resolution);
        self::assertNotNull($secondReview->resolved_at);
        self::assertSame($actor->playerId, (string) $secondReview->resolved_by_player_id);

        $command = app(BeginEvidenceCommit::class)->handle($actor->playerId, $occurrenceId, $secondReviewId);
        self::assertNotSame((string) $secondReview->semantic_fingerprint, $command->reportFingerprint);
    }

    public function test_unresolved_ocr_name_cannot_create_or_mutate_a_player_during_review(): void
    {
        [$actor, $allianceId, $occurrenceId] = $this->bearHunt();
        [$evidence, $extractionId] = $this->evidence($actor, $allianceId, $occurrenceId, 'identity');
        $before = Player::query()->count();
        $unknownPlayerId = (string) Str::ulid();

        try {
            app(SaveEvidenceReview::class)->handle(
                actorPlayerId: $actor->playerId,
                occurrenceId: $occurrenceId,
                evidenceId: (string) $evidence->id,
                extractionAttemptId: $extractionId,
                rows: [[
                    'row_ordinal' => 1,
                    'included' => true,
                    'player_id' => $unknownPlayerId,
                    'player_name' => 'OCR Unknown Governor',
                    'reported_rank' => 1,
                    'damage_points' => 100,
                    'correction_reason' => null,
                ]],
                reportTimestampText: '2026-08-22 13:05:23',
            );
            self::fail('Expected an unresolved OCR identity to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('rows', $exception->errors());
        }

        self::assertSame($before, Player::query()->count());
        self::assertFalse(Player::query()->whereKey($unknownPlayerId)->exists());
        self::assertSame(0, EvidenceReview::query()->where('evidence_id', $evidence->id)->count());
    }

    /** @return array{0:PlayerReference,1:string,2:string} */
    private function bearHunt(): array
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 59116);
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

    /** @return array{0:GameEvidence,1:string} */
    private function evidence(
        PlayerReference $actor,
        string $allianceId,
        string $occurrenceId,
        string $suffix,
    ): array {
        $sha256 = hash('sha256', 'duplicate-source-'.$suffix);
        $evidence = GameEvidence::query()->create([
            'alliance_id' => $allianceId,
            'occurrence_id' => $occurrenceId,
            'expected_kind' => EvidenceKind::BearHuntBattleReport,
            'kind' => EvidenceKind::BearHuntBattleReport,
            'lifecycle_status' => EvidenceLifecycleStatus::NeedsReview,
            'original_name' => $suffix.'.png',
            'disk' => 'local',
            'path' => 'evidence/'.$suffix.'.png',
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
            'classifier_key' => 'duplicate-fixture',
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
            'extractor_key' => 'duplicate-fixture',
            'extractor_version' => '1',
            'schema_version' => 'bear-hunt-report/1',
            'input_sha256' => $sha256,
            'overall_confidence' => 0.99,
            'field_count' => 0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        return [$evidence, (string) $extraction->id];
    }

    /** @return list<array{row_ordinal:int,included:bool,player_id:?string,player_name:string,reported_rank:?int,damage_points:?int,correction_reason:?string}> */
    private function rows(PlayerReference $actor): array
    {
        return [[
            'row_ordinal' => 1,
            'included' => true,
            'player_id' => $actor->playerId,
            'player_name' => $actor->currentName,
            'reported_rank' => 1,
            'damage_points' => 100,
            'correction_reason' => null,
        ]];
    }
}
