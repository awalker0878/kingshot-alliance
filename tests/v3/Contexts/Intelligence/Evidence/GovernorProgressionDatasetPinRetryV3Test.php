<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\Intelligence\Evidence\Actions\NormalizeGovernorProgressionEvidence;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\ProgressionNormalizationAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class GovernorProgressionDatasetPinRetryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_retry_normalization_reuses_the_first_dataset_pin_instead_of_latest(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 62411);
        $alliance = $scenario->alliance($actor);
        $entry = $scenario->roster($actor, $alliance);

        $progression = app(ProgressionDatasetQuery::class);
        $originalDataset = $progression->require('kingshot-2026-08-23-v1');
        $latestDataset = $progression->latest();
        self::assertNotSame($originalDataset->id, $latestDataset->id, 'The fixture requires a newer published dataset than the original pin.');

        $sha = hash('sha256', 'governor-progression-dataset-pin-retry');
        $evidence = GameEvidence::query()->create([
            'alliance_id' => $alliance->allianceId,
            'occurrence_id' => null,
            'roster_entry_id' => $entry->rosterEntryId,
            'transfer_plan_id' => null,
            'transfer_participant_id' => null,
            'expected_kind' => EvidenceKind::GovernorProfile,
            'kind' => EvidenceKind::GovernorProfile,
            'lifecycle_status' => EvidenceLifecycleStatus::Failed,
            'original_name' => 'governor-profile.png',
            'disk' => 'local',
            'path' => 'evidence/test/governor-profile.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'width' => 1080,
            'height' => 1920,
            'sha256' => $sha,
            'perceptual_hash' => null,
            'uploaded_by_player_id' => $actor->playerId,
            'scanned_at' => now(),
        ]);

        $firstClassification = $this->classification((string) $evidence->id, $sha);
        $firstExtraction = $this->extraction((string) $evidence->id, (string) $firstClassification->id, $sha);
        ProgressionNormalizationAttempt::query()->create([
            'evidence_id' => $evidence->id,
            'extraction_attempt_id' => $firstExtraction->id,
            'status' => EvidenceAttemptStatus::Failed,
            'normalizer_key' => 'governor-progression-catalogue-normalizer',
            'normalizer_version' => '1.0.0',
            'progression_dataset_id' => $originalDataset->id,
            'progression_dataset_checksum' => $originalDataset->checksum,
            'normalized_payload' => [],
            'warnings' => null,
            'failure_code' => 'fixture-failure',
            'started_at' => now()->subMinute(),
            'completed_at' => now()->subMinute(),
        ]);

        $retryClassification = $this->classification((string) $evidence->id, $sha);
        $retryExtraction = $this->extraction((string) $evidence->id, (string) $retryClassification->id, $sha);

        app(NormalizeGovernorProgressionEvidence::class)->handle(
            (string) $evidence->id,
            (string) $retryExtraction->id,
        );

        $retryNormalization = ProgressionNormalizationAttempt::query()
            ->where('evidence_id', $evidence->id)
            ->where('extraction_attempt_id', $retryExtraction->id)
            ->firstOrFail();

        self::assertSame(EvidenceAttemptStatus::Completed, $retryNormalization->status);
        self::assertSame($originalDataset->id, (string) $retryNormalization->progression_dataset_id);
        self::assertSame($originalDataset->checksum, (string) $retryNormalization->progression_dataset_checksum);
        self::assertNotSame($latestDataset->id, (string) $retryNormalization->progression_dataset_id);
    }

    private function classification(string $evidenceId, string $sha): EvidenceClassificationAttempt
    {
        return EvidenceClassificationAttempt::query()->create([
            'evidence_id' => $evidenceId,
            'status' => EvidenceAttemptStatus::Completed,
            'classifier_key' => 'governor-progression-schema-classifier',
            'classifier_version' => '1.0.1',
            'input_sha256' => $sha,
            'ocr_engine' => 'fixture',
            'ocr_version' => '1',
            'ocr_language' => 'eng',
            'ocr_payload' => [],
            'raw_text' => 'Governor Profile',
            'classified_kind' => EvidenceKind::GovernorProfile,
            'confidence' => 0.95,
            'reason' => 'fixture',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    private function extraction(string $evidenceId, string $classificationId, string $sha): EvidenceExtractionAttempt
    {
        return EvidenceExtractionAttempt::query()->create([
            'evidence_id' => $evidenceId,
            'classification_attempt_id' => $classificationId,
            'status' => EvidenceAttemptStatus::Completed,
            'extractor_key' => 'governor-progression-schema-extractor',
            'extractor_version' => '1.0.0',
            'schema_version' => 'governor-profile/1',
            'input_sha256' => $sha,
            'overall_confidence' => 0.95,
            'field_count' => 0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
