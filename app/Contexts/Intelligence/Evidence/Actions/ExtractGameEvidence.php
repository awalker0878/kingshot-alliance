<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Contracts\EvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Jobs\NormalizeGovernorProgressionEvidenceJob;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final readonly class ExtractGameEvidence
{
    public function __construct(
        private EvidenceExtractor $extractor,
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $evidenceId, string $classificationAttemptId): void
    {
        $attemptId = DB::transaction(function () use ($evidenceId, $classificationAttemptId): string {
            $evidence = GameEvidence::query()->whereKey($evidenceId)->lockForUpdate()->firstOrFail();
            $classification = EvidenceClassificationAttempt::query()
                ->whereKey($classificationAttemptId)
                ->where('evidence_id', $evidenceId)
                ->where('status', EvidenceAttemptStatus::Completed->value)
                ->sharedLock()
                ->firstOrFail();
            $kind = EvidenceKind::from((string) $classification->getRawOriginal('classified_kind'));
            if (! $this->extractor->supports($kind)) {
                throw new RuntimeException('No extractor supports the classified evidence kind.');
            }
            $status = EvidenceLifecycleStatus::from((string) $evidence->getRawOriginal('lifecycle_status'));
            if (! in_array($status, [EvidenceLifecycleStatus::Classified, EvidenceLifecycleStatus::Failed], true)) {
                return '';
            }
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Extracting])->save();
            $attempt = EvidenceExtractionAttempt::query()->create([
                'evidence_id' => $evidence->id,
                'classification_attempt_id' => $classification->id,
                'status' => EvidenceAttemptStatus::Running,
                'extractor_key' => $this->extractor->key($kind),
                'extractor_version' => $this->extractor->version($kind),
                'schema_version' => $this->extractor->schemaVersion($kind),
                'input_sha256' => $evidence->sha256,
                'overall_confidence' => 0,
                'field_count' => 0,
                'started_at' => now(),
            ]);
            $actor = $this->players->find((string) $evidence->uploaded_by_player_id);
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'extraction_attempt_id' => (string) $attempt->id,
                'classification_attempt_id' => (string) $classification->id,
                'kind' => $kind->value,
                'extractor_key' => $this->extractor->key($kind),
                'extractor_version' => $this->extractor->version($kind),
                'schema_version' => $this->extractor->schemaVersion($kind),
            ];
            $this->audit->record('evidence.extraction_started', $actor, $evidence, (string) $evidence->alliance_id, $metadata);
            $this->outbox->record('evidence.extraction_started', (string) $evidence->alliance_id, $evidence, $metadata);

            return (string) $attempt->id;
        });
        if ($attemptId === '') {
            return;
        }

        try {
            $classification = EvidenceClassificationAttempt::query()->findOrFail($classificationAttemptId);
            if (! is_array($classification->ocr_payload)) {
                throw new RuntimeException('Classification OCR provenance is unavailable.');
            }
            $kind = EvidenceKind::from((string) $classification->getRawOriginal('classified_kind'));
            $document = OcrDocument::fromArray($classification->ocr_payload);
            $fields = $this->extractor->extract($kind, $document);
            $overall = $fields === [] ? 0.0 : array_sum(array_map(static fn ($field): float => $field->confidence, $fields)) / count($fields);

            $needsNormalization = DB::transaction(function () use ($evidenceId, $attemptId, $fields, $overall, $kind): bool {
                $evidence = GameEvidence::query()->whereKey($evidenceId)->lockForUpdate()->firstOrFail();
                $attempt = EvidenceExtractionAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
                if ($attempt->getRawOriginal('status') !== EvidenceAttemptStatus::Running->value) {
                    throw new RuntimeException('Extraction attempt is no longer running.');
                }
                foreach ($fields as $field) {
                    EvidenceExtractedField::query()->create([
                        'extraction_attempt_id' => $attempt->id,
                        'field_key' => $field->fieldKey,
                        'row_ordinal' => $field->rowOrdinal,
                        'raw_text' => $field->rawText,
                        'normalized_value' => $field->normalizedValue,
                        'data_type' => $field->dataType,
                        'confidence' => $field->confidence,
                        'bounding_box' => $field->boundingBox,
                        'warnings' => $field->warnings,
                    ]);
                }
                $attempt->forceFill([
                    'status' => EvidenceAttemptStatus::Completed,
                    'overall_confidence' => max(0.0, min(1.0, $overall)),
                    'field_count' => count($fields),
                    'completed_at' => now(),
                ])->save();
                if (! $kind->isGovernorProgression()) {
                    $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::NeedsReview])->save();
                }
                $actor = $this->players->find((string) $evidence->uploaded_by_player_id);
                $metadata = [
                    'evidence_id' => (string) $evidence->id,
                    'extraction_attempt_id' => (string) $attempt->id,
                    'kind' => $kind->value,
                    'extractor_key' => $this->extractor->key($kind),
                    'extractor_version' => $this->extractor->version($kind),
                    'schema_version' => $this->extractor->schemaVersion($kind),
                    'field_count' => count($fields),
                    'overall_confidence' => $overall,
                    'normalization_required' => $kind->isGovernorProgression(),
                ];
                $this->audit->record('evidence.extracted', $actor, $evidence, (string) $evidence->alliance_id, $metadata);
                $this->outbox->record('evidence.extracted', (string) $evidence->alliance_id, $evidence, $metadata);

                return $kind->isGovernorProgression();
            });
            if ($needsNormalization) {
                NormalizeGovernorProgressionEvidenceJob::dispatch($evidenceId, $attemptId);
            }
        } catch (Throwable $exception) {
            DB::transaction(function () use ($evidenceId, $attemptId, $exception): void {
                $evidence = GameEvidence::query()->whereKey($evidenceId)->lockForUpdate()->first();
                $attempt = EvidenceExtractionAttempt::query()->whereKey($attemptId)->lockForUpdate()->first();
                if ($attempt instanceof EvidenceExtractionAttempt && $attempt->getRawOriginal('status') === EvidenceAttemptStatus::Running->value) {
                    $attempt->forceFill([
                        'status' => EvidenceAttemptStatus::Failed,
                        'failure_code' => $this->failureCode($exception),
                        'completed_at' => now(),
                    ])->save();
                }
                if ($evidence instanceof GameEvidence) {
                    $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Failed])->save();
                    $actor = $this->players->find((string) $evidence->uploaded_by_player_id);
                    $metadata = [
                        'evidence_id' => (string) $evidence->id,
                        'extraction_attempt_id' => $attemptId,
                        'failure_code' => $this->failureCode($exception),
                    ];
                    $this->audit->record('evidence.extraction_failed', $actor, $evidence, (string) $evidence->alliance_id, $metadata);
                    $this->outbox->record('evidence.extraction_failed', (string) $evidence->alliance_id, $evidence, $metadata);
                }
            });
            throw $exception;
        }
    }

    private function failureCode(Throwable $exception): string
    {
        return substr(hash('sha256', $exception::class.':'.$exception->getMessage()), 0, 24);
    }
}
