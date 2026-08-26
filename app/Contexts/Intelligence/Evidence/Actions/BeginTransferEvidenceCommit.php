<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEvidenceTargetQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceReviewKingdom;
use App\Contexts\Intelligence\Evidence\Services\TransferEvidenceSchemaRegistry;
use App\Contexts\Intelligence\Evidence\ValueObjects\ReviewedTransferEvidenceCommitCommand;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class BeginTransferEvidenceCommit
{
    public function __construct(
        private TransferEvidenceTargetQuery $targets,
        private TransferEvidenceSchemaRegistry $schemas,
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $planId,
        string $participantId,
        string $reviewId,
    ): ReviewedTransferEvidenceCommitCommand {
        $this->targets->authorizeAllianceManage($actorPlayerId, $allianceId);

        return DB::transaction(function () use ($actorPlayerId, $allianceId, $planId, $participantId, $reviewId): ReviewedTransferEvidenceCommitCommand {
            $this->targets->authorizeAllianceManage($actorPlayerId, $allianceId);
            $actor = $this->players->lockCurrent($actorPlayerId);
            $review = TransferEvidenceReview::query()
                ->whereKey($reviewId)
                ->where('alliance_id', $allianceId)
                ->where('transfer_plan_id', $planId)
                ->where('transfer_participant_id', $participantId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($review->status !== EvidenceReviewStatus::Approved) {
                throw ValidationException::withMessages(['review' => 'Resolve the semantic duplicate or approve a newer review before committing Transfer Evidence.']);
            }
            $latest = TransferEvidenceReview::query()
                ->where('evidence_id', $review->evidence_id)
                ->orderByDesc('revision_number')
                ->orderByDesc('id')
                ->sharedLock()
                ->first();
            if (! $latest instanceof TransferEvidenceReview || (string) $latest->id !== (string) $review->id) {
                throw ValidationException::withMessages(['review' => 'Only the latest approved Transfer Evidence review may be committed.']);
            }

            $evidence = GameEvidence::query()
                ->whereKey($review->evidence_id)
                ->where('alliance_id', $allianceId)
                ->whereNull('occurrence_id')
                ->where('transfer_plan_id', $planId)
                ->where('transfer_participant_id', $participantId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($evidence->lifecycle_status === EvidenceLifecycleStatus::Deleted || $evidence->kind !== $review->evidence_kind) {
                throw ValidationException::withMessages(['evidence' => 'The reviewed Transfer Evidence is no longer commit-eligible.']);
            }
            $schema = $this->schemas->require($review->evidence_kind);
            if ($schema->version !== (string) $review->schema_version) {
                throw ValidationException::withMessages(['review' => 'The approved review schema version is no longer the registered destination contract.']);
            }

            $idempotencyKey = hash('sha256', implode(':', [
                'transfer-evidence-review',
                (string) $review->id,
                (string) $review->semantic_fingerprint,
                (string) $review->schema_version,
            ]));
            $attempt = TransferEvidenceCommitAttempt::query()
                ->where('transfer_review_id', $review->id)
                ->where('idempotency_key', $idempotencyKey)
                ->whereIn('status', [EvidenceCommitStatus::Pending->value, EvidenceCommitStatus::Succeeded->value])
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();
            if (! $attempt instanceof TransferEvidenceCommitAttempt) {
                if ($evidence->lifecycle_status !== EvidenceLifecycleStatus::Approved) {
                    throw ValidationException::withMessages(['evidence' => 'Transfer Evidence must be approved before starting a new destination commit.']);
                }
                $attempt = TransferEvidenceCommitAttempt::query()->create([
                    'evidence_id' => $evidence->id,
                    'transfer_review_id' => $review->id,
                    'alliance_id' => $allianceId,
                    'status' => EvidenceCommitStatus::Pending,
                    'idempotency_key' => $idempotencyKey,
                    'destination_action' => $schema->destinationAction,
                    'started_by_player_id' => $actorPlayerId,
                    'started_at' => now(),
                ]);
            }

            if ($attempt->status === EvidenceCommitStatus::Pending) {
                $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Committing])->save();
                $metadata = [
                    'evidence_id' => (string) $evidence->id,
                    'review_id' => (string) $review->id,
                    'commit_attempt_id' => (string) $attempt->id,
                    'evidence_kind' => $review->evidence_kind->value,
                    'schema_version' => (string) $review->schema_version,
                    'destination_action' => $schema->destinationAction,
                ];
                $this->audit->record('evidence.transfer_commit_started', $actor, $evidence, $allianceId, $metadata);
                $this->outbox->record('evidence.transfer_commit_started', $allianceId, $evidence, $metadata);
            }

            $kingdomNumbers = TransferEvidenceReviewKingdom::query()
                ->where('review_id', $review->id)
                ->orderBy('ordinal')
                ->pluck('kingdom_number')
                ->map(static fn ($number): int => (int) $number)
                ->all();

            return new ReviewedTransferEvidenceCommitCommand(
                commitAttemptId: (string) $attempt->id,
                evidenceId: (string) $evidence->id,
                reviewId: (string) $review->id,
                allianceId: $allianceId,
                transferPlanId: $planId,
                transferParticipantId: $participantId,
                transferWindowId: (string) $review->transfer_window_id,
                targetKingdomId: $review->target_kingdom_id === null ? null : (string) $review->target_kingdom_id,
                kind: $review->evidence_kind,
                schemaVersion: (string) $review->schema_version,
                idempotencyKey: $idempotencyKey,
                observedAt: $review->observed_at->toIso8601String(),
                validUntil: $review->valid_until?->toIso8601String(),
                governorPower: $review->governor_power,
                transferScore: $review->transfer_score,
                transferPassesAvailable: $review->transfer_passes_available,
                transferPassesRequired: $review->transfer_passes_required,
                invitationStatus: $review->invitation_status,
                targetPowerCap: $review->target_power_cap,
                kingdomClassification: $review->kingdom_classification,
                officialGroupIdentifier: $review->official_group_identifier,
                officialGroupKingdomNumbers: $kingdomNumbers,
            );
        });
    }
}
