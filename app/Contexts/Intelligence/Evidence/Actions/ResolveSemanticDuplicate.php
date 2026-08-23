<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Results\Queries\BearHuntEvidenceTargetQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ResolveSemanticDuplicate
{
    public function __construct(
        private BearHuntEvidenceTargetQuery $targets,
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $reviewId,
        string $justification,
    ): void {
        $justification = trim($justification);
        if (mb_strlen($justification) < 10 || mb_strlen($justification) > 1000) {
            throw ValidationException::withMessages(['justification' => 'Explain in 10 to 1000 characters why these are distinct battle reports.']);
        }
        $target = $this->targets->authorizeManage($actorPlayerId, $occurrenceId);

        DB::transaction(function () use ($actorPlayerId, $reviewId, $justification, $target): void {
            $this->targets->authorizeManage($actorPlayerId, $target->occurrenceId);
            $actor = $this->players->lockCurrent($actorPlayerId);
            $review = EvidenceReview::query()
                ->whereKey($reviewId)
                ->where('alliance_id', $target->allianceId)
                ->where('occurrence_id', $target->occurrenceId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($review->getRawOriginal('status') !== EvidenceReviewStatus::DuplicateBlocked->value || $review->semantic_duplicate_review_id === null) {
                throw ValidationException::withMessages(['review' => 'This review is not blocked by a semantic duplicate.']);
            }
            $review->forceFill([
                'status' => EvidenceReviewStatus::Approved,
                'duplicate_resolution' => $justification,
                'resolved_by_player_id' => $actorPlayerId,
                'resolved_at' => now(),
            ])->save();
            $evidence = GameEvidence::query()
                ->whereKey($review->evidence_id)
                ->where('alliance_id', $target->allianceId)
                ->where('occurrence_id', $target->occurrenceId)
                ->lockForUpdate()
                ->firstOrFail();
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Approved])->save();
            $metadata = ['evidence_id' => (string) $evidence->id, 'review_id' => (string) $review->id, 'duplicate_review_id' => (string) $review->semantic_duplicate_review_id];
            $this->audit->record('evidence.semantic_duplicate_resolved', $actor, $evidence, $target->allianceId, $metadata);
            $this->outbox->record('evidence.semantic_duplicate_resolved', $target->allianceId, $evidence, $metadata);
        });
    }
}
