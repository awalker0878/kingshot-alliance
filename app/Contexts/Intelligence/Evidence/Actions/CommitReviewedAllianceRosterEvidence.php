<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\AllianceRosterEvidence;
use App\Contexts\Intelligence\Evidence\Models\AllianceRosterEvidenceReview;
use App\Contexts\Intelligence\Roster\Actions\RecordAllianceRosterObservationBatch;
use App\Contexts\Intelligence\Roster\ValueObjects\AllianceRosterObservationBatchReceipt;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class CommitReviewedAllianceRosterEvidence
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private RecordAllianceRosterObservationBatch $destination,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $allianceId, string $reviewId): AllianceRosterObservationBatchReceipt
    {
        $prepared = $this->prepare($actorPlayerId, $allianceId, $reviewId);

        if ($prepared['completed'] instanceof AllianceRosterObservationBatchReceipt) {
            return $prepared['completed'];
        }

        $attemptId = $this->requiredString($prepared['attemptId'], 'The roster commit attempt was not prepared.');
        $evidenceId = $this->requiredString($prepared['evidenceId'], 'The roster evidence was not prepared.');
        $preparedReviewId = $this->requiredString($prepared['reviewId'], 'The roster review was not prepared.');
        $schemaVersion = $this->requiredString($prepared['schemaVersion'], 'The roster schema was not prepared.');
        $capturedAt = $this->requiredString($prepared['capturedAt'], 'The roster capture time was not prepared.');
        $idempotencyKey = $this->requiredString($prepared['idempotencyKey'], 'The roster idempotency key was not prepared.');

        try {
            $receipt = $this->destination->handle(
                $actorPlayerId,
                $allianceId,
                $evidenceId,
                $preparedReviewId,
                $schemaVersion,
                $capturedAt,
                $prepared['rows'],
                $idempotencyKey,
            );
        } catch (Throwable $exception) {
            DB::table('evidence_alliance_roster_commit_attempts')->where('id', $attemptId)->update([
                'status' => EvidenceAttemptStatus::Failed->value,
                'failure_code' => class_basename($exception),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
            throw $exception;
        }

        DB::transaction(function () use ($actorPlayerId, $allianceId, $attemptId, $evidenceId, $preparedReviewId, $receipt): void {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $evidence = AllianceRosterEvidence::query()
                ->whereKey($evidenceId)
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->firstOrFail();
            DB::table('evidence_alliance_roster_commit_attempts')->where('id', $attemptId)->update([
                'status' => EvidenceAttemptStatus::Completed->value,
                'destination_batch_id' => $receipt->batchId,
                'destination_receipt' => json_encode($receipt->toArray(), JSON_THROW_ON_ERROR),
                'failure_code' => null,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Committed])->save();
            $metadata = [
                'evidence_id' => $evidenceId,
                'review_id' => $preparedReviewId,
                'destination_batch_id' => $receipt->batchId,
                'row_count' => $receipt->rowCount,
            ];
            $this->audit->record('evidence.alliance_roster_committed', $actor, $evidence, $allianceId, $metadata);
            $this->outbox->record('evidence.alliance_roster_committed', $allianceId, $evidence, $metadata);
        });

        return $receipt;
    }

    /**
     * @return array{
     *   completed: AllianceRosterObservationBatchReceipt|null,
     *   attemptId: string|null,
     *   evidenceId: string|null,
     *   reviewId: string|null,
     *   schemaVersion: string|null,
     *   capturedAt: string|null,
     *   rows: list<array<string, mixed>>,
     *   idempotencyKey: string|null
     * }
     */
    private function prepare(string $actorPlayerId, string $allianceId, string $reviewId): array
    {
        return DB::transaction(function () use ($actorPlayerId, $allianceId, $reviewId): array {
            $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $review = AllianceRosterEvidenceReview::query()
                ->whereKey($reviewId)
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($review->status !== EvidenceReviewStatus::Approved) {
                throw ValidationException::withMessages(['review' => 'Only an approved roster review can be committed.']);
            }

            $evidence = AllianceRosterEvidence::query()
                ->whereKey($review->evidence_id)
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->firstOrFail();
            if (! in_array($evidence->lifecycle_status, [EvidenceLifecycleStatus::Approved, EvidenceLifecycleStatus::Committed], true)) {
                throw ValidationException::withMessages(['evidence' => 'The approved screenshot provenance is no longer valid.']);
            }

            $idempotencyKey = hash('sha256', 'alliance-roster-v1|'.$allianceId.'|'.$reviewId.'|'.$review->semantic_fingerprint);
            $attempt = DB::table('evidence_alliance_roster_commit_attempts')
                ->where('review_id', $reviewId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($attempt !== null && (string) $attempt->status === EvidenceAttemptStatus::Completed->value && $attempt->destination_batch_id !== null) {
                $decoded = json_decode((string) $attempt->destination_receipt, true, 512, JSON_THROW_ON_ERROR);
                $rowCount = is_array($decoded) ? (int) ($decoded['row_count'] ?? 0) : 0;

                return [
                    'completed' => new AllianceRosterObservationBatchReceipt((string) $attempt->destination_batch_id, $rowCount),
                    'attemptId' => null,
                    'evidenceId' => null,
                    'reviewId' => null,
                    'schemaVersion' => null,
                    'capturedAt' => null,
                    'rows' => [],
                    'idempotencyKey' => null,
                ];
            }

            if ($attempt === null) {
                $attemptId = (string) Str::ulid();
                DB::table('evidence_alliance_roster_commit_attempts')->insert([
                    'id' => $attemptId,
                    'evidence_id' => $evidence->id,
                    'review_id' => $reviewId,
                    'alliance_id' => $allianceId,
                    'status' => EvidenceAttemptStatus::Running->value,
                    'idempotency_key' => $idempotencyKey,
                    'destination_action' => 'record_alliance_roster_observation_batch',
                    'destination_batch_id' => null,
                    'destination_receipt' => null,
                    'failure_code' => null,
                    'started_by_player_id' => $actorPlayerId,
                    'started_at' => now(),
                    'completed_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $attemptId = (string) $attempt->id;
                DB::table('evidence_alliance_roster_commit_attempts')->where('id', $attemptId)->update([
                    'status' => EvidenceAttemptStatus::Running->value,
                    'failure_code' => null,
                    'updated_at' => now(),
                ]);
            }

            $payloadRows = $review->payload['rows'] ?? [];
            $rows = [];
            if (is_array($payloadRows)) {
                foreach ($payloadRows as $row) {
                    if (is_array($row)) {
                        $rows[] = $row;
                    }
                }
            }

            return [
                'completed' => null,
                'attemptId' => $attemptId,
                'evidenceId' => $evidence->id,
                'reviewId' => $review->id,
                'schemaVersion' => $review->schema_version,
                'capturedAt' => $review->captured_at->toIso8601String(),
                'rows' => $rows,
                'idempotencyKey' => $idempotencyKey,
            ];
        });
    }

    private function requiredString(?string $value, string $message): string
    {
        if ($value === null || $value === '') {
            throw ValidationException::withMessages(['evidence' => $message]);
        }

        return $value;
    }
}
