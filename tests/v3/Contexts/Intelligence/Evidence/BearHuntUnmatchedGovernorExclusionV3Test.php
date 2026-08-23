<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Evidence\Actions\SaveEvidenceReview;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReviewRow;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Queries\BearHuntUnmatchedGovernorQuery;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class BearHuntUnmatchedGovernorExclusionV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_intentionally_excluded_governor_row_leaves_unmatched_queue_through_evidence_owner_review(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61407);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $occurrence = $this->occurrence($actor, $alliance);
        [$evidence, $extraction] = $this->evidence(
            $alliance->allianceId,
            (string) $occurrence->id,
            $actor->playerId,
        );

        $before = app(BearHuntUnmatchedGovernorQuery::class)->forOccurrence(
            $actor->playerId,
            (string) $occurrence->id,
        );
        self::assertCount(1, $before);
        self::assertSame('Decorative Header', $before[0]['rows'][0]['observedName']);

        $reviewId = app(SaveEvidenceReview::class)->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: (string) $occurrence->id,
            evidenceId: (string) $evidence->id,
            extractionAttemptId: (string) $extraction->id,
            rows: [[
                'row_ordinal' => 1,
                'included' => false,
                'player_id' => null,
                'player_name' => 'Decorative Header',
                'reported_rank' => 4,
                'damage_points' => 450000,
                'correction_reason' => 'This extracted row is not a Governor result.',
            ]],
        );

        $evidence->refresh();
        $reviewRow = EvidenceReviewRow::query()->where('review_id', $reviewId)->firstOrFail();
        self::assertFalse((bool) $reviewRow->included);
        self::assertSame(EvidenceLifecycleStatus::Approved, $evidence->lifecycle_status);
        self::assertSame([], app(BearHuntUnmatchedGovernorQuery::class)->forOccurrence(
            $actor->playerId,
            (string) $occurrence->id,
        ));
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
            title: 'Unmatched Governor Exclusion Fixture',
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);

        return EventOccurrence::query()->findOrFail($created->firstOccurrenceId);
    }

    /** @return array{GameEvidence,EvidenceExtractionAttempt} */
    private function evidence(
        string $allianceId,
        string $occurrenceId,
        string $uploaderPlayerId,
    ): array {
        $sha256 = hash('sha256', 'bear-hunt-unmatched-excluded');
        $evidence = GameEvidence::query()->create([
            'alliance_id' => $allianceId,
            'occurrence_id' => $occurrenceId,
            'expected_kind' => EvidenceKind::BearHuntBattleReport,
            'kind' => EvidenceKind::BearHuntBattleReport,
            'lifecycle_status' => EvidenceLifecycleStatus::NeedsReview,
            'original_name' => 'excluded-row.png',
            'disk' => 'local',
            'path' => 'evidence/tests/excluded-row.png',
            'mime_type' => 'image/png',
            'size_bytes' => 100,
            'width' => 1080,
            'height' => 1920,
            'sha256' => $sha256,
            'perceptual_hash' => substr(hash('sha256', 'excluded-row-visual'), 0, 16),
            'uploaded_by_player_id' => $uploaderPlayerId,
            'scanned_at' => now(),
        ]);
        $classification = EvidenceClassificationAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'status' => EvidenceAttemptStatus::Completed,
            'classifier_key' => 'bear-hunt-unmatched-excluded-test',
            'classifier_version' => '1',
            'input_sha256' => $sha256,
            'ocr_engine' => 'fixture',
            'ocr_version' => '1',
            'ocr_language' => 'eng',
            'classified_kind' => EvidenceKind::BearHuntBattleReport,
            'confidence' => 0.9,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $extraction = EvidenceExtractionAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'classification_attempt_id' => $classification->id,
            'status' => EvidenceAttemptStatus::Completed,
            'extractor_key' => 'bear-hunt-ranking-v1',
            'extractor_version' => '1.1.0',
            'schema_version' => 'bear-hunt-report/1',
            'input_sha256' => $sha256,
            'overall_confidence' => 0.8,
            'field_count' => 3,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        foreach ([
            ['player_name', 'Decorative Header', 'string', 0.70],
            ['rank', '4', 'integer', 0.80],
            ['damage', '450000', 'integer', 0.90],
        ] as [$fieldKey, $value, $dataType, $confidence]) {
            EvidenceExtractedField::query()->create([
                'extraction_attempt_id' => $extraction->id,
                'field_key' => $fieldKey,
                'row_ordinal' => 1,
                'raw_text' => $value,
                'normalized_value' => $value,
                'data_type' => $dataType,
                'confidence' => $confidence,
            ]);
        }

        return [$evidence, $extraction];
    }
}
