<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Evidence\Actions\SaveEvidenceReview;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReviewRow;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class EvidenceReviewV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_manual_correction_creates_revision_without_overwriting_machine_value_or_confidence(): void
    {
        [$actor, $allianceId, $occurrenceId] = $this->bearHunt();
        $hash = hash('sha256', 'review-source');
        $evidence = GameEvidence::query()->create([
            'alliance_id' => $allianceId,
            'occurrence_id' => $occurrenceId,
            'expected_kind' => EvidenceKind::BearHuntBattleReport,
            'kind' => EvidenceKind::BearHuntBattleReport,
            'lifecycle_status' => EvidenceLifecycleStatus::NeedsReview,
            'original_name' => 'review.png',
            'disk' => 'local',
            'path' => 'evidence/review.png',
            'mime_type' => 'image/png',
            'size_bytes' => 100,
            'width' => 1080,
            'height' => 1920,
            'sha256' => $hash,
            'uploaded_by_player_id' => $actor->playerId,
            'scanned_at' => now(),
        ]);
        $classification = EvidenceClassificationAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'status' => EvidenceAttemptStatus::Completed,
            'classifier_key' => 'fixture',
            'classifier_version' => '1',
            'input_sha256' => $hash,
            'classified_kind' => EvidenceKind::BearHuntBattleReport,
            'confidence' => 0.95,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $extraction = EvidenceExtractionAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'classification_attempt_id' => $classification->id,
            'status' => EvidenceAttemptStatus::Completed,
            'extractor_key' => 'fixture',
            'extractor_version' => '1',
            'schema_version' => 'bear-hunt-report/1',
            'input_sha256' => $hash,
            'overall_confidence' => 0.91,
            'field_count' => 2,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $name = EvidenceExtractedField::query()->create([
            'extraction_attempt_id' => $extraction->id,
            'field_key' => 'player_name',
            'row_ordinal' => 1,
            'raw_text' => $actor->currentName,
            'normalized_value' => $actor->currentName,
            'data_type' => 'string',
            'confidence' => 0.93,
        ]);
        $damage = EvidenceExtractedField::query()->create([
            'extraction_attempt_id' => $extraction->id,
            'field_key' => 'damage',
            'row_ordinal' => 1,
            'raw_text' => '1,156,200',
            'normalized_value' => '1156200',
            'data_type' => 'integer',
            'confidence' => 0.91,
        ]);

        $reviewId = app(SaveEvidenceReview::class)->handle(
            actorPlayerId: $actor->playerId,
            evidenceId: (string) $evidence->id,
            extractionAttemptId: (string) $extraction->id,
            rows: [[
                'row_ordinal' => 1,
                'included' => true,
                'player_id' => $actor->playerId,
                'player_name' => $actor->currentName,
                'reported_rank' => 1,
                'damage_points' => 1156260,
                'correction_reason' => 'The OCR confused the final digit; the screenshot visibly shows 260.',
            ]],
            reportTimestampText: '2026-08-22 13:05:23',
        );

        $review = EvidenceReview::query()->findOrFail($reviewId);
        $row = EvidenceReviewRow::query()->where('review_id', $reviewId)->firstOrFail();
        self::assertSame(1, (int) $review->revision_number);
        self::assertSame(1156260, (int) $row->damage_points);
        self::assertTrue((bool) $row->damage_corrected);
        self::assertSame((string) $damage->id, (string) $row->source_damage_field_id);
        self::assertSame((string) $name->id, (string) $row->source_name_field_id);
        self::assertSame('1156200', (string) $damage->refresh()->normalized_value);
        self::assertEqualsWithDelta(0.91, (float) $damage->confidence, 0.001);

        $secondReviewId = app(SaveEvidenceReview::class)->handle(
            actorPlayerId: $actor->playerId,
            evidenceId: (string) $evidence->id,
            extractionAttemptId: (string) $extraction->id,
            rows: [[
                'row_ordinal' => 1,
                'included' => true,
                'player_id' => $actor->playerId,
                'player_name' => $actor->currentName,
                'reported_rank' => 1,
                'damage_points' => 1156260,
                'correction_reason' => 'Second review confirms the corrected visible damage value.',
            ]],
            reportTimestampText: '2026-08-22 13:05:23',
        );

        self::assertSame(2, (int) EvidenceReview::query()->findOrFail($secondReviewId)->revision_number);
        self::assertSame(2, EvidenceReview::query()->where('evidence_id', $evidence->id)->count());
    }

    /** @return array{0:PlayerReference,1:string,2:string} */
    private function bearHunt(): array
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 59103);
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
}
