<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\SpatialEvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\SpatialEvidenceReview;
use App\Contexts\Intelligence\Observations\Actions\RecordSpatialObservationEvidence;
use App\Contexts\Intelligence\Observations\ValueObjects\SpatialObservationRecordResult;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class CommitReviewedSpatialEvidence
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private RecordSpatialObservationEvidence $destination,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {
    }

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $kingdomId,
        string $reviewId,
    ): SpatialObservationRecordResult {
        $prepared = DB::transaction(function () use ($actorPlayerId, $allianceId, $kingdomId, $reviewId): array {
            [$scope, $actor] = $this->writeState->authorize(
                $actorPlayerId,
                $allianceId,
                IntelligencePermission::KingdomManage,
            );
            if ($scope->kingdomId !== $kingdomId) {
                throw ValidationException::withMessages([
                    'kingdom_id' => 'The reviewed observation belongs to historical Kingdom context.',
                ]);
            }

            $review = SpatialEvidenceReview::query()
                ->whereKey($reviewId)
                ->where('alliance_id', $allianceId)
                ->where('kingdom_id', $kingdomId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($review->status !== EvidenceReviewStatus::Approved) {
                throw ValidationException::withMessages([
                    'review' => 'Only an approved Territory spatial review can be committed.',
                ]);
            }

            $latestId = SpatialEvidenceReview::query()
                ->where('evidence_id', $review->evidence_id)
                ->orderByDesc('revision_number')
                ->orderByDesc('id')
                ->value('id');
            if ((string) $latestId !== (string) $review->id) {
                throw ValidationException::withMessages([
                    'review' => 'Commit the latest approved review revision instead.',
                ]);
            }

            $evidence = GameEvidence::query()
                ->whereKey($review->evidence_id)
                ->where('alliance_id', $allianceId)
                ->where('kingdom_id', $kingdomId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($evidence->kind !== EvidenceKind::TerritoryMapObservation
                || $evidence->expected_kind !== EvidenceKind::TerritoryMapObservation
                || $evidence->lifecycle_status === EvidenceLifecycleStatus::Deleted) {
                throw ValidationException::withMessages([
                    'evidence' => 'The approved Territory screenshot provenance is no longer valid.',
                ]);
            }

            $idempotencyKey = hash('sha256', implode(':', [
                'territory-spatial',
                (string) $review->id,
                (string) $review->semantic_fingerprint,
                (string) $review->map_dataset_checksum,
            ]));
            $attempt = SpatialEvidenceCommitAttempt::query()
                ->where('spatial_review_id', $review->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($attempt instanceof SpatialEvidenceCommitAttempt
                && $attempt->status === EvidenceCommitStatus::Succeeded
                && $attempt->destination_receipt_id !== null
                && is_array($attempt->destination_receipt)) {
                return ['completed' => new SpatialObservationRecordResult(
                    receiptId: (string) $attempt->destination_receipt_id,
                    observationId: (string) ($attempt->destination_receipt['observation_id'] ?? ''),
                    idempotentReplay: true,
                )];
            }

            if (! $attempt instanceof SpatialEvidenceCommitAttempt) {
                $attempt = SpatialEvidenceCommitAttempt::query()->create([
                    'evidence_id' => $evidence->id,
                    'spatial_review_id' => $review->id,
                    'alliance_id' => $allianceId,
                    'kingdom_id' => $kingdomId,
                    'status' => EvidenceCommitStatus::Pending,
                    'idempotency_key' => $idempotencyKey,
                    'destination_action' => 'RecordSpatialObservationEvidence',
                    'started_by_player_id' => $actorPlayerId,
                    'started_at' => now(),
                ]);
            } else {
                $attempt->forceFill([
                    'status' => EvidenceCommitStatus::Pending,
                    'failure_code' => null,
                    'started_by_player_id' => $actorPlayerId,
                    'started_at' => now(),
                    'completed_at' => null,
                ])->save();
            }

            $evidence->forceFill([
                'lifecycle_status' => EvidenceLifecycleStatus::Committing,
            ])->save();
            $this->audit->record(
                'evidence.territory_spatial_commit_started',
                $actor,
                $evidence,
                $allianceId,
                [
                    'review_id' => $reviewId,
                    'commit_attempt_id' => (string) $attempt->id,
                ],
            );

            return [
                'attempt_id' => (string) $attempt->id,
                'evidence_id' => (string) $evidence->id,
                'review_id' => (string) $review->id,
                'schema_version' => (string) $review->schema_version,
                'dataset_id' => (string) $review->map_dataset_id,
                'dataset_checksum' => (string) $review->map_dataset_checksum,
                'captured_at' => $review->captured_at->toIso8601String(),
                'coverage_kind' => $review->coverage_kind,
                'completeness' => $review->completeness,
                'coverage_bounds' => is_array($review->coverage_bounds) ? $review->coverage_bounds : null,
                'objects' => is_array($review->payload['objects'] ?? null) ? $review->payload['objects'] : [],
                'idempotency_key' => $idempotencyKey,
            ];
        });
        if (array_key_exists('completed', $prepared)) {
            return $prepared['completed'];
        }

        try {
            $receipt = $this->destination->handle(
                actorPlayerId: $actorPlayerId,
                allianceId: $allianceId,
                kingdomId: $kingdomId,
                evidenceId: (string) $prepared['evidence_id'],
                reviewId: (string) $prepared['review_id'],
                schemaVersion: (string) $prepared['schema_version'],
                mapDatasetId: (string) $prepared['dataset_id'],
                mapDatasetChecksum: (string) $prepared['dataset_checksum'],
                capturedAt: (string) $prepared['captured_at'],
                coverageKind: $prepared['coverage_kind'],
                completeness: $prepared['completeness'],
                coverageBounds: $prepared['coverage_bounds'],
                objects: $prepared['objects'],
                idempotencyKey: (string) $prepared['idempotency_key'],
            );
            DB::transaction(function () use ($actorPlayerId, $allianceId, $prepared, $receipt): void {
                [, $actor] = $this->writeState->authorize(
                    $actorPlayerId,
                    $allianceId,
                    IntelligencePermission::KingdomManage,
                );
                $attempt = SpatialEvidenceCommitAttempt::query()
                    ->whereKey($prepared['attempt_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $evidence = GameEvidence::query()
                    ->whereKey($prepared['evidence_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $attempt->forceFill([
                    'status' => EvidenceCommitStatus::Succeeded,
                    'destination_receipt_id' => $receipt->receiptId,
                    'destination_receipt' => [
                        'receipt_id' => $receipt->receiptId,
                        'observation_id' => $receipt->observationId,
                        'idempotent_replay' => $receipt->idempotentReplay,
                    ],
                    'failure_code' => null,
                    'completed_at' => now(),
                ])->save();
                $evidence->forceFill([
                    'lifecycle_status' => EvidenceLifecycleStatus::Committed,
                ])->save();
                $event = $receipt->idempotentReplay
                    ? 'evidence.territory_spatial_commit_recovered'
                    : 'evidence.territory_spatial_committed';
                $metadata = [
                    'review_id' => (string) $prepared['review_id'],
                    'observation_id' => $receipt->observationId,
                    'receipt_id' => $receipt->receiptId,
                ];
                $this->audit->record($event, $actor, $evidence, $allianceId, $metadata);
                $this->outbox->record($event, $allianceId, $evidence, $metadata);
            });

            return $receipt;
        } catch (Throwable $exception) {
            try {
                DB::transaction(function () use ($actorPlayerId, $allianceId, $prepared, $exception): void {
                    [, $actor] = $this->writeState->authorize(
                        $actorPlayerId,
                        $allianceId,
                        IntelligencePermission::KingdomManage,
                    );
                    $attempt = SpatialEvidenceCommitAttempt::query()
                        ->whereKey($prepared['attempt_id'])
                        ->lockForUpdate()
                        ->first();
                    $evidence = GameEvidence::query()
                        ->whereKey($prepared['evidence_id'])
                        ->lockForUpdate()
                        ->first();
                    $failureCode = substr(
                        hash('sha256', $exception::class.':'.$exception->getMessage()),
                        0,
                        24,
                    );
                    if ($attempt instanceof SpatialEvidenceCommitAttempt
                        && $attempt->status !== EvidenceCommitStatus::Succeeded) {
                        $attempt->forceFill([
                            'status' => EvidenceCommitStatus::Failed,
                            'failure_code' => $failureCode,
                            'completed_at' => now(),
                        ])->save();
                    }
                    if ($evidence instanceof GameEvidence
                        && $evidence->lifecycle_status !== EvidenceLifecycleStatus::Committed) {
                        $evidence->forceFill([
                            'lifecycle_status' => EvidenceLifecycleStatus::Approved,
                        ])->save();
                        $this->audit->record(
                            'evidence.territory_spatial_commit_failed',
                            $actor,
                            $evidence,
                            $allianceId,
                            ['failure_code' => $failureCode],
                        );
                    }
                });
            } catch (Throwable) {
                // Preserve the original destination/acknowledgement failure.
            }

            throw $exception;
        }
    }
}
