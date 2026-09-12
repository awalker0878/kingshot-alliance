<?php

declare(strict_types=1);

namespace Tests\Contexts\Intelligence\Evidence\Feature;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\GovernorProgressionEvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\GovernorProgressionEvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\ProgressionNormalizationAttempt;
use App\Contexts\Intelligence\Evidence\Queries\GovernorProgressionEvidenceSummaryQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class GovernorProgressionEvidenceSummaryBudgetV3Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_workspace_query_count_stays_bounded_with_history_and_a_full_page(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-08T12:00:00Z'));
        $scenario = new ScenarioFactory;
        $actor = $scenario->player($scenario->account()->userId);
        $alliance = $scenario->alliance($actor);
        $entry = $scenario->roster($actor, $alliance);
        $first = $this->evidence($alliance->allianceId, $entry->rosterEntryId, $actor->playerId);
        [$single, $singleQueries] = $this->summaries($alliance->allianceId, $entry->rosterEntryId);
        self::assertCount(1, $single);
        $expected = [$first['evidence'] => $first];
        for ($index = 0; $index < 30; $index++) {
            $item = $this->evidence($alliance->allianceId, $entry->rosterEntryId, $actor->playerId);
            $expected[$item['evidence']] = $item;
        }
        $foreignActor = $scenario->player($scenario->account()->userId);
        $foreignAlliance = $scenario->alliance($foreignActor);
        $foreignEntry = $scenario->roster($foreignActor, $foreignAlliance);
        $foreign = $this->evidence($foreignAlliance->allianceId, $foreignEntry->rosterEntryId, $foreignActor->playerId);
        [$page, $pageQueries] = $this->summaries($alliance->allianceId, $entry->rosterEntryId);

        self::assertLessThanOrEqual(8, $singleQueries);
        self::assertSame($singleQueries, $pageQueries, 'Adding Evidence records must not add per-item database round trips.');
        self::assertCount(30, $page);
        $expectedIds = array_keys($expected);
        rsort($expectedIds, SORT_STRING);
        self::assertSame(array_slice($expectedIds, 0, 30), array_column($page, 'id'));
        self::assertNotContains($foreign['evidence'], array_column($page, 'id'));
        foreach ($page as $summary) {
            $latest = $expected[$summary['id']];
            self::assertSame($latest['classification'], $summary['classification']['id']);
            self::assertSame($latest['extraction'], $summary['extraction']['id']);
            self::assertSame($latest['normalization'], $summary['normalization']['id']);
            self::assertSame($latest['review'], $summary['review']['id']);
            self::assertSame(3, $summary['review']['revisionNumber']);
            self::assertSame($latest['commit'], $summary['commit']['id']);
            self::assertSame(['building_level', 'building_name'], array_column($summary['extraction']['fields'], 'fieldKey'));
            self::assertSame(['3', 'Academy'], array_column($summary['extraction']['fields'], 'normalizedValue'));
        }
    }

    public function test_empty_scope_returns_without_loading_attempt_tables(): void
    {
        [$summaries, $queries] = $this->summaries('absent-alliance', 'absent-roster');
        self::assertSame([], $summaries);
        self::assertSame(1, $queries);
    }

    /** @return array{list<array<string,mixed>>,int} */
    private function summaries(string $allianceId, string $entryId): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $summaries = app(GovernorProgressionEvidenceSummaryQuery::class)->forRosterEntry($allianceId, $entryId);

            return [$summaries, count(DB::getQueryLog())];
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    /** @return array{evidence:string,classification:string,extraction:string,normalization:string,review:string,commit:string} */
    private function evidence(string $allianceId, string $entryId, string $actorId): array
    {
        $evidence = GameEvidence::query()->create([
            'alliance_id' => $allianceId,
            'roster_entry_id' => $entryId,
            'occurrence_id' => null,
            'expected_kind' => EvidenceKind::GovernorBuildings,
            'kind' => EvidenceKind::GovernorBuildings,
            'lifecycle_status' => EvidenceLifecycleStatus::Committed,
            'original_name' => 'budget.png',
            'disk' => 'local',
            'path' => 'evidence/budget/'.Str::ulid().'.png',
            'mime_type' => 'image/png',
            'size_bytes' => 100,
            'width' => 1080,
            'height' => 1920,
            'sha256' => hash('sha256', (string) Str::ulid()),
            'uploaded_by_player_id' => $actorId,
            'scanned_at' => now(),
        ]);
        $latest = [];
        for ($revision = 1; $revision <= 3; $revision++) {
            $classification = EvidenceClassificationAttempt::query()->create([
                'evidence_id' => $evidence->id,
                'status' => EvidenceAttemptStatus::Completed,
                'classifier_key' => 'query-budget-fixture',
                'classifier_version' => (string) $revision,
                'input_sha256' => $evidence->sha256,
                'classified_kind' => EvidenceKind::GovernorBuildings,
                'confidence' => 0.99,
                'started_at' => now(),
                'completed_at' => now(),
            ]);
            $extraction = EvidenceExtractionAttempt::query()->create([
                'evidence_id' => $evidence->id,
                'classification_attempt_id' => $classification->id,
                'status' => EvidenceAttemptStatus::Completed,
                'extractor_key' => 'query-budget-fixture',
                'extractor_version' => (string) $revision,
                'schema_version' => 'governor-buildings/1',
                'input_sha256' => $evidence->sha256,
                'overall_confidence' => 0.99,
                'field_count' => 2,
                'started_at' => now(),
                'completed_at' => now(),
            ]);
            foreach (['building_name' => 'Academy', 'building_level' => (string) $revision] as $key => $value) {
                EvidenceExtractedField::query()->create([
                    'extraction_attempt_id' => $extraction->id,
                    'field_key' => $key,
                    'row_ordinal' => 1,
                    'raw_text' => $value,
                    'normalized_value' => $value,
                    'data_type' => 'text',
                    'confidence' => 0.99,
                ]);
            }
            $normalization = ProgressionNormalizationAttempt::query()->create([
                'evidence_id' => $evidence->id,
                'extraction_attempt_id' => $extraction->id,
                'status' => EvidenceAttemptStatus::Completed,
                'normalizer_key' => 'query-budget-fixture',
                'normalizer_version' => (string) $revision,
                'progression_dataset_id' => 'query-budget-pin',
                'progression_dataset_checksum' => hash('sha256', 'query-budget-pin'),
                'normalized_payload' => ['fields' => []],
                'started_at' => now(),
                'completed_at' => now(),
            ]);
            $review = GovernorProgressionEvidenceReview::query()->create([
                'evidence_id' => $evidence->id,
                'normalization_attempt_id' => $normalization->id,
                'alliance_id' => $allianceId,
                'roster_entry_id' => $entryId,
                'player_id' => $actorId,
                'evidence_kind' => EvidenceKind::GovernorBuildings,
                'schema_version' => 'governor-buildings/1',
                'progression_dataset_id' => $normalization->progression_dataset_id,
                'progression_dataset_checksum' => $normalization->progression_dataset_checksum,
                'revision_number' => $revision,
                'status' => EvidenceReviewStatus::Approved,
                'captured_at' => now(),
                'payload' => ['states' => [['subject_id' => 'academy', 'state_id' => 'level:'.$revision, 'level' => $revision]]],
                'semantic_fingerprint' => hash('sha256', $evidence->id.':'.$revision),
                'reviewed_by_player_id' => $actorId,
                'reviewed_at' => now(),
                'created_at' => now()->subDays($revision),
            ]);
            $commit = GovernorProgressionEvidenceCommitAttempt::query()->create([
                'evidence_id' => $evidence->id,
                'governor_review_id' => $review->id,
                'alliance_id' => $allianceId,
                'status' => EvidenceCommitStatus::Succeeded,
                'idempotency_key' => hash('sha256', $review->id.':commit'),
                'destination_action' => 'RecordStructuredProgressionEvidence',
                'started_by_player_id' => $actorId,
                'started_at' => now(),
                'completed_at' => now(),
            ]);
            $latest = ['evidence' => (string) $evidence->id, 'classification' => (string) $classification->id, 'extraction' => (string) $extraction->id, 'normalization' => (string) $normalization->id, 'review' => (string) $review->id, 'commit' => (string) $commit->id];
        }

        return $latest;
    }
}
