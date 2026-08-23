<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReviewRow;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\ValueObjects\ReviewedBearHuntCommitCommand;
use App\Contexts\Operations\Results\Queries\BearHuntEvidenceTargetQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class BeginEvidenceCommit
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
    ): ReviewedBearHuntCommitCommand {
        $target = $this->targets->authorizeManage($actorPlayerId, $occurrenceId);

        return DB::transaction(function () use ($actorPlayerId, $reviewId, $target): ReviewedBearHuntCommitCommand {
            $this->targets->authorizeManage($actorPlayerId, $target->occurrenceId);
            $actor = $this->players->lockCurrent($actorPlayerId);
            $review = EvidenceReview::query()
                ->whereKey($reviewId)
                ->where('alliance_id', $target->allianceId)
                ->where('occurrence_id', $target->occurrenceId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($review->getRawOriginal('status') !== EvidenceReviewStatus::Approved->value) {
                throw ValidationException::withMessages(['review' => 'Resolve the review before committing this battle report.']);
            }
            $evidence = GameEvidence::query()
                ->whereKey($review->evidence_id)
                ->where('alliance_id', $target->allianceId)
                ->where('occurrence_id', $target->occurrenceId)
                ->lockForUpdate()
                ->firstOrFail();
            $idempotencyKey = hash('sha256', 'bear-hunt-review:'.(string) $review->id.':'.(string) $review->semantic_fingerprint);
            $pending = EvidenceCommitAttempt::query()
                ->where('review_id', $review->id)
                ->where('idempotency_key', $idempotencyKey)
                ->whereIn('status', [EvidenceCommitStatus::Pending->value, EvidenceCommitStatus::Succeeded->value])
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();
            $attempt = $pending instanceof EvidenceCommitAttempt ? $pending : EvidenceCommitAttempt::query()->create([
                'evidence_id' => $evidence->id,
                'review_id' => $review->id,
                'status' => EvidenceCommitStatus::Pending,
                'idempotency_key' => $idempotencyKey,
                'destination_context' => 'operations.results',
                'started_at' => now(),
            ]);

            $rows = EvidenceReviewRow::query()
                ->where('review_id', $review->id)
                ->where('included', true)
                ->orderBy('row_ordinal')
                ->get();
            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['review' => 'At least one reviewed Governor must be included before commit.']);
            }
            $entries = [];
            foreach ($rows as $row) {
                if ($row->player_id === null || $row->damage_points === null) {
                    throw ValidationException::withMessages(['review' => 'Every included row requires a resolved Governor and damage value.']);
                }
                $entries[] = [
                    'player_id' => (string) $row->player_id,
                    'reported_rank' => $row->reported_rank === null ? null : (int) $row->reported_rank,
                    'damage_points' => (int) $row->damage_points,
                ];
            }

            if ($attempt->getRawOriginal('status') === EvidenceCommitStatus::Pending->value) {
                $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Committing])->save();
                $metadata = [
                    'evidence_id' => (string) $evidence->id,
                    'review_id' => (string) $review->id,
                    'commit_attempt_id' => (string) $attempt->id,
                    'entry_count' => count($entries),
                ];
                $this->audit->record('evidence.commit_started', $actor, $evidence, $target->allianceId, $metadata);
                $this->outbox->record('evidence.commit_started', $target->allianceId, $evidence, $metadata);
            }

            $acceptanceFingerprint = $review->duplicate_resolution === null
                ? (string) $review->semantic_fingerprint
                : hash('sha256', (string) $review->semantic_fingerprint.':'.(string) $review->id.':'.(string) $review->duplicate_resolution);

            return new ReviewedBearHuntCommitCommand(
                commitAttemptId: (string) $attempt->id,
                evidenceId: (string) $evidence->id,
                reviewId: (string) $review->id,
                occurrenceId: $target->occurrenceId,
                idempotencyKey: $idempotencyKey,
                reportFingerprint: $acceptanceFingerprint,
                reportTimestampText: $review->report_timestamp_text === null ? null : (string) $review->report_timestamp_text,
                entries: $entries,
            );
        });
    }
}
