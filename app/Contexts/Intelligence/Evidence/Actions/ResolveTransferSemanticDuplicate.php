<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEvidenceTargetQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceReview;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ResolveTransferSemanticDuplicate
{
    public function __construct(
        private TransferEvidenceTargetQuery $targets,
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
        string $justification,
    ): void {
        $this->targets->authorizeManage($actorPlayerId, $allianceId, $planId, $participantId);
        $justification = trim($justification);
        if (mb_strlen($justification) < 8 || mb_strlen($justification) > 1000) {
            throw ValidationException::withMessages(['duplicate_resolution' => 'Explain why this semantically duplicate screenshot represents a legitimate separate observation.']);
        }

        DB::transaction(function () use ($actorPlayerId, $allianceId, $planId, $participantId, $reviewId, $justification): void {
            $this->targets->authorizeManage($actorPlayerId, $allianceId, $planId, $participantId);
            $actor = $this->players->lockCurrent($actorPlayerId);
            $review = TransferEvidenceReview::query()
                ->whereKey($reviewId)
                ->where('alliance_id', $allianceId)
                ->where('transfer_plan_id', $planId)
                ->where('transfer_participant_id', $participantId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($review->status !== EvidenceReviewStatus::DuplicateBlocked || $review->semantic_duplicate_review_id === null) {
                throw ValidationException::withMessages(['review' => 'This review is not blocked by a semantic duplicate.']);
            }
            $latestId = TransferEvidenceReview::query()
                ->where('evidence_id', $review->evidence_id)
                ->orderByDesc('revision_number')
                ->orderByDesc('id')
                ->value('id');
            if ((string) $latestId !== (string) $review->id) {
                throw ValidationException::withMessages(['review' => 'Resolve the latest review revision instead.']);
            }

            $review->forceFill([
                'status' => EvidenceReviewStatus::Approved,
                'duplicate_resolution' => $justification,
                'resolved_by_player_id' => $actorPlayerId,
                'resolved_at' => now(),
            ])->save();
            $evidence = GameEvidence::query()->whereKey($review->evidence_id)->lockForUpdate()->firstOrFail();
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Approved])->save();
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'review_id' => (string) $review->id,
                'duplicate_review_id' => (string) $review->semantic_duplicate_review_id,
                'evidence_kind' => $review->evidence_kind->value,
            ];
            $this->audit->record('evidence.transfer_semantic_duplicate_resolved', $actor, $evidence, $allianceId, $metadata);
            $this->outbox->record('evidence.transfer_semantic_duplicate_resolved', $allianceId, $evidence, $metadata);
        });
    }
}
