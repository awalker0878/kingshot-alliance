<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\SpatialEvidenceReview;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ResolveSpatialSemanticDuplicate
{
    public function __construct(private AllianceIntelligenceWriteState $writeState, private AuditRecorder $audit) {}

    public function handle(string $actorPlayerId, string $allianceId, string $kingdomId, string $reviewId, string $justification): void
    {
        $justification = trim($justification);
        if (mb_strlen($justification) < 8 || mb_strlen($justification) > 1000) {
            throw ValidationException::withMessages(['justification' => 'Duplicate resolution requires a justification between 8 and 1,000 characters.']);
        }
        DB::transaction(function () use ($actorPlayerId, $allianceId, $kingdomId, $reviewId, $justification): void {
            [$scope, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            if ($scope->kingdomId !== $kingdomId) {
                throw ValidationException::withMessages(['kingdom_id' => 'The reviewed observation belongs to historical Kingdom context.']);
            }
            $review = SpatialEvidenceReview::query()->whereKey($reviewId)->where('alliance_id', $allianceId)->where('kingdom_id', $kingdomId)->lockForUpdate()->firstOrFail();
            if ($review->status !== EvidenceReviewStatus::DuplicateBlocked) {
                return;
            }
            $latestId = SpatialEvidenceReview::query()->where('evidence_id', $review->evidence_id)->orderByDesc('revision_number')->orderByDesc('id')->value('id');
            if ((string) $latestId !== (string) $review->id) {
                throw ValidationException::withMessages(['review' => 'Only the latest review revision can resolve a semantic duplicate.']);
            }
            $review->forceFill([
                'status' => EvidenceReviewStatus::Approved,
                'duplicate_resolution' => $justification,
                'resolved_by_player_id' => $actorPlayerId,
                'resolved_at' => now(),
            ])->save();
            $evidence = GameEvidence::query()->whereKey($review->evidence_id)->lockForUpdate()->firstOrFail();
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Approved])->save();
            $this->audit->record('evidence.territory_spatial_duplicate_resolved', $actor, $evidence, $allianceId, ['review_id' => $reviewId, 'kingdom_id' => $kingdomId]);
        });
    }
}
