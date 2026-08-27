<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\GovernorProgressionEvidenceReview;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ResolveGovernorProgressionSemanticDuplicate
{
    public function __construct(
        private AllianceIntelligenceAuthorization $authorization,
        private AllianceIntelligenceWriteState $writeState,
        private RosterEntryQuery $roster,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $rosterEntryId,
        string $reviewId,
        string $justification,
    ): void {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }
        $this->roster->requireActiveOrTracked($allianceId, $rosterEntryId);
        $justification = trim($justification);
        if (mb_strlen($justification) < 8 || mb_strlen($justification) > 1000) {
            throw ValidationException::withMessages(['duplicate_resolution' => 'Explain why this semantically duplicate screenshot represents a legitimate separate observation.']);
        }

        DB::transaction(function () use ($actorPlayerId, $allianceId, $rosterEntryId, $reviewId, $justification): void {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $entry = $this->roster->requireActiveOrTracked($allianceId, $rosterEntryId);
            $review = GovernorProgressionEvidenceReview::query()
                ->whereKey($reviewId)
                ->where('alliance_id', $allianceId)
                ->where('roster_entry_id', $rosterEntryId)
                ->where('player_id', $entry->playerId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($review->status !== EvidenceReviewStatus::DuplicateBlocked || $review->semantic_duplicate_review_id === null) {
                throw ValidationException::withMessages(['review' => 'This review is not blocked by a semantic duplicate.']);
            }
            $latestId = GovernorProgressionEvidenceReview::query()
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
            $evidence = GameEvidence::query()
                ->whereKey($review->evidence_id)
                ->where('alliance_id', $allianceId)
                ->where('roster_entry_id', $rosterEntryId)
                ->lockForUpdate()
                ->firstOrFail();
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Approved])->save();
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'review_id' => (string) $review->id,
                'duplicate_review_id' => (string) $review->semantic_duplicate_review_id,
                'roster_entry_id' => $rosterEntryId,
                'target_player_id' => $entry->playerId,
                'evidence_kind' => $review->evidence_kind->value,
            ];
            $this->audit->record('evidence.governor_progression_semantic_duplicate_resolved', $actor, $evidence, $allianceId, $metadata);
            $this->outbox->record('evidence.governor_progression_semantic_duplicate_resolved', $allianceId, $evidence, $metadata);
        });
    }
}
