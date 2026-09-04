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
        $prepared = DB::transaction(function () use ($actorPlayerId, $allianceId, $reviewId): array {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $review = AllianceRosterEvidenceReview::query()
                ->whereKey($reviewId)
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($review->status !== EvidenceReviewStatus::Approved) {
                throw ValidationException::withMessages(['review' => 'Only an approved roster review can be committed.']);
            }

            $evidence = AllianceRosterEvidence::query()
                ->whereKey((string) $review->evidence_id)
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
                if ((string) $attempt->status === EvidenceAttemptStatus::Completed->value && $attempt->destination_batch_id !== null) {
                    $receipt = json_decode((string) $attempt->destination_receipt, true, 512, JSON_THROW_ON_ERROR);
                    return ['completed' => new AllianceRosterObservationBatchReceipt((string) $attempt->destination_batch_id, (int) ($receipt['row_count'] ?? 0))];
                }
                DB::table('evidence_alliance_roster_commit_attempts')->where('id', $attemptId)->update([
                    'status' => EvidenceAttemptStatus::Running->value,
                    'failure_code' => null,
                    'updated_at' => now(),
                ]);
            }

            return [
                'completed' => null,
                'attemptId' => $attemptId,
                'actor' => $actor,
                'evidenceId' => (string) $evidence->id,
                'reviewId' => (string) $review->id,
                'schemaVersion' => (string) $review->schema_version,
                'capturedAt' => $review->captured_at->toIso8601String(),
                'rows' => (array) (($review->payload ?? [])['rows'] ?? []),
                'idempotencyKey' => $idempotencyKey,
            ];
        });

        if ($prepared['completed'] instanceof AllianceRosterObservationBatchReceipt) {
            return $prepared['completed'];
        }

        try {
            $receipt = $this->destination->handle(
                $actorPlayerId,
                $allianceId,
                $prepared['evidenceId'],
                $prepared['reviewId'],
                $prepared['schemaVersion'],
                $prepared['capturedAt'],
                $prepared['rows'],
                $prepared['idempotencyKey'],
            );
        } catch (Throwable $exception) {
            DB::table('evidence_alliance_roster_commit_attempts')->where('id', $prepared['attemptId'])->update([
                'status' => EvidenceAttemptStatus::Failed->value,
                'failure_code' => class_basename($exception),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
            throw $exception;
        }

        DB::transaction(function () use ($prepared, $receipt, $allianceId): void {
            [, $actor] = $this->writeState->authorize((string) $prepared['actor']->playerId, $allianceId, IntelligencePermission::KingdomManage);
            $evidence = AllianceRosterEvidence::query()->whereKey($prepared['evidenceId'])->where('alliance_id', $allianceId)->lockForUpdate()->firstOrFail();
            DB::table('evidence_alliance_roster_commit_attempts')->where('id', $prepared['attemptId'])->update([
                'status' => EvidenceAttemptStatus::Completed->value,
                'destination_batch_id' => $receipt->batchId,
                'destination_receipt' => json_encode($receipt->toArray(), JSON_THROW_ON_ERROR),
                'failure_code' => null,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Committed])->save();
            $metadata = [
                'evidence_id' => $prepared['evidenceId'],
                'review_id' => $prepared['reviewId'],
                'destination_batch_id' => $receipt->batchId,
                'row_count' => $receipt->rowCount,
            ];
            $this->audit->record('evidence.alliance_roster_committed', $actor, $evidence, $allianceId, $metadata);
            $this->outbox->record('evidence.alliance_roster_committed', $allianceId, $evidence, $metadata);
        });

        return $receipt;
    }
}
