<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\ProgressionNormalizationAttempt;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final readonly class NormalizeGovernorProgressionEvidence
{
    private const NORMALIZER_KEY = 'governor-progression-catalogue-normalizer';

    private const NORMALIZER_VERSION = '1.0.0';

    public function __construct(
        private ProgressionDatasetQuery $progression,
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $evidenceId, string $extractionAttemptId): void
    {
        $dataset = $this->progression->latest();
        $attemptId = DB::transaction(function () use ($evidenceId, $extractionAttemptId, $dataset): string {
            $evidence = GameEvidence::query()->whereKey($evidenceId)->lockForUpdate()->firstOrFail();
            $kind = EvidenceKind::from((string) $evidence->getRawOriginal('kind'));
            if (!$kind->isGovernorProgression()) {
                throw new RuntimeException('Only Governor Progression Evidence can be normalized against Progression.');
            }
            $extraction = EvidenceExtractionAttempt::query()
                ->whereKey($extractionAttemptId)
                ->where('evidence_id', $evidenceId)
                ->where('status', EvidenceAttemptStatus::Completed->value)
                ->sharedLock()
                ->firstOrFail();
            $existing = ProgressionNormalizationAttempt::query()
                ->where('evidence_id', $evidenceId)
                ->where('extraction_attempt_id', $extraction->id)
                ->where('progression_dataset_id', $dataset->id)
                ->where('progression_dataset_checksum', $dataset->checksum)
                ->where('status', EvidenceAttemptStatus::Completed->value)
                ->latest('created_at')
                ->first();
            if ($existing instanceof ProgressionNormalizationAttempt) {
                $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::NeedsReview])->save();

                return '';
            }

            $attempt = ProgressionNormalizationAttempt::query()->create([
                'evidence_id' => $evidence->id,
                'extraction_attempt_id' => $extraction->id,
                'status' => EvidenceAttemptStatus::Running,
                'normalizer_key' => self::NORMALIZER_KEY,
                'normalizer_version' => self::NORMALIZER_VERSION,
                'progression_dataset_id' => $dataset->id,
                'progression_dataset_checksum' => $dataset->checksum,
                'normalized_payload' => [],
                'warnings' => null,
                'started_at' => now(),
            ]);
            $actor = $this->players->find((string) $evidence->uploaded_by_player_id);
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'normalization_attempt_id' => (string) $attempt->id,
                'extraction_attempt_id' => (string) $extraction->id,
                'kind' => $kind->value,
                'progression_dataset_id' => $dataset->id,
                'progression_dataset_checksum' => $dataset->checksum,
                'normalizer_key' => self::NORMALIZER_KEY,
                'normalizer_version' => self::NORMALIZER_VERSION,
            ];
            $this->audit->record('evidence.progression_normalization_started', $actor, $evidence, (string) $evidence->alliance_id, $metadata);
            $this->outbox->record('evidence.progression_normalization_started', (string) $evidence->alliance_id, $evidence, $metadata);

            return (string) $attempt->id;
        });
        if ($attemptId === '') {
            return;
        }

        try {
            $evidence = GameEvidence::query()->findOrFail($evidenceId);
            $kind = EvidenceKind::from((string) $evidence->getRawOriginal('kind'));
            $fields = EvidenceExtractedField::query()
                ->where('extraction_attempt_id', $extractionAttemptId)
                ->orderBy('row_ordinal')
                ->orderBy('field_key')
                ->get();
            $payloadFields = [];
            $warnings = [];
            foreach ($fields as $field) {
                $fieldKey = (string) $field->field_key;
                $candidate = $field->normalized_value === null ? null : (string) $field->normalized_value;
                $canonicalId = null;
                $identityConfidence = null;
                $fieldWarnings = is_array($field->warnings) ? array_values($field->warnings) : [];
                if ($fieldKey === 'hero_name' && $candidate !== null) {
                    $canonicalId = $this->progression->canonicalHeroId($candidate, $dataset);
                    $identityConfidence = $canonicalId === null ? 0.0 : 1.0;
                    if ($canonicalId === null) {
                        $fieldWarnings[] = 'No exact Hero identity exists in the pinned Progression dataset; human review must select a canonical Hero.';
                        $warnings[] = 'unmatched_hero_identity';
                    }
                }
                $payloadFields[] = [
                    'field_id' => (string) $field->id,
                    'field_key' => $fieldKey,
                    'row_ordinal' => (int) $field->row_ordinal,
                    'raw_text' => (string) $field->raw_text,
                    'candidate' => $candidate,
                    'confidence' => (float) $field->confidence,
                    'bounding_box' => is_array($field->bounding_box) ? $field->bounding_box : null,
                    'warnings' => $fieldWarnings,
                    'canonical_id' => $canonicalId,
                    'identity_confidence' => $identityConfidence,
                ];
            }
            $payload = [
                'kind' => $kind->value,
                'dataset' => [
                    'id' => $dataset->id,
                    'version' => $dataset->datasetVersion,
                    'checksum' => $dataset->checksum,
                ],
                'fields' => $payloadFields,
            ];

            DB::transaction(function () use ($evidenceId, $attemptId, $payload, $warnings, $dataset, $kind): void {
                $evidence = GameEvidence::query()->whereKey($evidenceId)->lockForUpdate()->firstOrFail();
                $attempt = ProgressionNormalizationAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
                if ($attempt->getRawOriginal('status') !== EvidenceAttemptStatus::Running->value) {
                    throw new RuntimeException('Normalization attempt is no longer running.');
                }
                $attempt->forceFill([
                    'status' => EvidenceAttemptStatus::Completed,
                    'normalized_payload' => $payload,
                    'warnings' => array_values(array_unique($warnings)),
                    'completed_at' => now(),
                ])->save();
                $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::NeedsReview])->save();
                $actor = $this->players->find((string) $evidence->uploaded_by_player_id);
                $metadata = [
                    'evidence_id' => (string) $evidence->id,
                    'normalization_attempt_id' => (string) $attempt->id,
                    'kind' => $kind->value,
                    'progression_dataset_id' => $dataset->id,
                    'progression_dataset_checksum' => $dataset->checksum,
                    'field_count' => count($payload['fields']),
                    'warning_count' => count(array_unique($warnings)),
                ];
                $this->audit->record('evidence.progression_normalized', $actor, $evidence, (string) $evidence->alliance_id, $metadata);
                $this->outbox->record('evidence.progression_normalized', (string) $evidence->alliance_id, $evidence, $metadata);
            });
        } catch (Throwable $exception) {
            DB::transaction(function () use ($evidenceId, $attemptId, $exception): void {
                $evidence = GameEvidence::query()->whereKey($evidenceId)->lockForUpdate()->first();
                $attempt = ProgressionNormalizationAttempt::query()->whereKey($attemptId)->lockForUpdate()->first();
                $failureCode = substr(hash('sha256', $exception::class.':'.$exception->getMessage()), 0, 24);
                if ($attempt instanceof ProgressionNormalizationAttempt && $attempt->getRawOriginal('status') === EvidenceAttemptStatus::Running->value) {
                    $attempt->forceFill([
                        'status' => EvidenceAttemptStatus::Failed,
                        'failure_code' => $failureCode,
                        'completed_at' => now(),
                    ])->save();
                }
                if ($evidence instanceof GameEvidence) {
                    $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Failed])->save();
                    $actor = $this->players->find((string) $evidence->uploaded_by_player_id);
                    $metadata = [
                        'evidence_id' => (string) $evidence->id,
                        'normalization_attempt_id' => $attemptId,
                        'failure_code' => $failureCode,
                    ];
                    $this->audit->record('evidence.progression_normalization_failed', $actor, $evidence, (string) $evidence->alliance_id, $metadata);
                    $this->outbox->record('evidence.progression_normalization_failed', (string) $evidence->alliance_id, $evidence, $metadata);
                }
            });
            throw $exception;
        }
    }
}
