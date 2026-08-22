<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Contracts\EvidenceClassifier;
use App\Contexts\Intelligence\Evidence\Contracts\OcrEngine;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Jobs\ExtractGameEvidenceJob;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final readonly class ClassifyGameEvidence
{
    public function __construct(
        private OcrEngine $ocr,
        private EvidenceClassifier $classifier,
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $evidenceId): void
    {
        $attemptId = DB::transaction(function () use ($evidenceId): string {
            $evidence = GameEvidence::query()->whereKey($evidenceId)->lockForUpdate()->firstOrFail();
            $status = EvidenceLifecycleStatus::from((string) $evidence->getRawOriginal('lifecycle_status'));
            if (! in_array($status, [EvidenceLifecycleStatus::Uploaded, EvidenceLifecycleStatus::Failed], true)) {
                return '';
            }
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Classifying])->save();
            $attempt = EvidenceClassificationAttempt::query()->create([
                'evidence_id' => $evidence->id,
                'status' => EvidenceAttemptStatus::Running,
                'classifier_key' => $this->classifier->key(),
                'classifier_version' => $this->classifier->version(),
                'input_sha256' => $evidence->sha256,
                'classified_kind' => EvidenceKind::Unknown,
                'confidence' => 0,
                'started_at' => now(),
            ]);
            $actor = $this->players->find((string) $evidence->uploaded_by_player_id);
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'classification_attempt_id' => (string) $attempt->id,
                'classifier_key' => $this->classifier->key(),
                'classifier_version' => $this->classifier->version(),
            ];
            $this->audit->record('evidence.classification_started', $actor, $evidence, (string) $evidence->alliance_id, $metadata);
            $this->outbox->record('evidence.classification_started', (string) $evidence->alliance_id, $evidence, $metadata);

            return (string) $attempt->id;
        });
        if ($attemptId === '') {
            return;
        }

        try {
            $evidence = GameEvidence::query()->findOrFail($evidenceId);
            $document = $this->ocr->recognize($evidence);
            $expected = EvidenceKind::from((string) $evidence->getRawOriginal('expected_kind'));
            $decision = $this->classifier->classify($expected, $document);

            DB::transaction(function () use ($evidenceId, $attemptId, $document, $decision): void {
                $evidence = GameEvidence::query()->whereKey($evidenceId)->lockForUpdate()->firstOrFail();
                $attempt = EvidenceClassificationAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
                if ($attempt->getRawOriginal('status') !== EvidenceAttemptStatus::Running->value) {
                    throw new RuntimeException('Classification attempt is no longer running.');
                }
                $attempt->forceFill([
                    'status' => EvidenceAttemptStatus::Completed,
                    'ocr_engine' => $document->engine,
                    'ocr_version' => $document->engineVersion,
                    'ocr_language' => $document->language,
                    'ocr_payload' => $document->toArray(),
                    'raw_text' => $document->text(),
                    'classified_kind' => $decision->kind,
                    'confidence' => $decision->confidence,
                    'reason' => $decision->reason,
                    'completed_at' => now(),
                ])->save();
                $evidence->forceFill([
                    'kind' => $decision->kind,
                    'lifecycle_status' => $decision->kind === EvidenceKind::Unknown
                        ? EvidenceLifecycleStatus::Unsupported
                        : EvidenceLifecycleStatus::Classified,
                ])->save();
                $actor = $this->players->find((string) $evidence->uploaded_by_player_id);
                $metadata = [
                    'evidence_id' => (string) $evidence->id,
                    'classification_attempt_id' => (string) $attempt->id,
                    'kind' => $decision->kind->value,
                    'confidence' => $decision->confidence,
                    'classifier_key' => $this->classifier->key(),
                    'classifier_version' => $this->classifier->version(),
                    'ocr_engine' => $document->engine,
                    'ocr_version' => $document->engineVersion,
                ];
                $this->audit->record('evidence.classified', $actor, $evidence, (string) $evidence->alliance_id, $metadata);
                $this->outbox->record('evidence.classified', (string) $evidence->alliance_id, $evidence, $metadata);
            });

            if ($decision->kind !== EvidenceKind::Unknown) {
                ExtractGameEvidenceJob::dispatch($evidenceId, $attemptId);
            }
        } catch (Throwable $exception) {
            DB::transaction(function () use ($evidenceId, $attemptId, $exception): void {
                $evidence = GameEvidence::query()->whereKey($evidenceId)->lockForUpdate()->first();
                $attempt = EvidenceClassificationAttempt::query()->whereKey($attemptId)->lockForUpdate()->first();
                if ($attempt instanceof EvidenceClassificationAttempt && $attempt->getRawOriginal('status') === EvidenceAttemptStatus::Running->value) {
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
                        'classification_attempt_id' => $attemptId,
                        'failure_code' => $this->failureCode($exception),
                    ];
                    $this->audit->record('evidence.classification_failed', $actor, $evidence, (string) $evidence->alliance_id, $metadata);
                    $this->outbox->record('evidence.classification_failed', (string) $evidence->alliance_id, $evidence, $metadata);
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
