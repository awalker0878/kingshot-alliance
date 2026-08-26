<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEvidenceTargetQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceCommitAttempt;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class FailTransferEvidenceCommit
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
        string $commitAttemptId,
        string $failureCode,
    ): void {
        $this->targets->authorizeAllianceManage($actorPlayerId, $allianceId);

        DB::transaction(function () use ($actorPlayerId, $allianceId, $commitAttemptId, $failureCode): void {
            $this->targets->authorizeAllianceManage($actorPlayerId, $allianceId);
            $actor = $this->players->lockCurrent($actorPlayerId);
            $attempt = TransferEvidenceCommitAttempt::query()
                ->whereKey($commitAttemptId)
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->first();
            if (! $attempt instanceof TransferEvidenceCommitAttempt || $attempt->status !== EvidenceCommitStatus::Pending) {
                return;
            }
            $attempt->forceFill([
                'status' => EvidenceCommitStatus::Failed,
                'failure_code' => $failureCode,
                'completed_at' => now(),
            ])->save();
            $evidence = GameEvidence::query()->whereKey($attempt->evidence_id)->lockForUpdate()->first();
            if (! $evidence instanceof GameEvidence) {
                return;
            }
            if ($evidence->lifecycle_status !== EvidenceLifecycleStatus::Deleted) {
                $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Approved])->save();
            }
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'review_id' => (string) $attempt->transfer_review_id,
                'commit_attempt_id' => (string) $attempt->id,
                'failure_code' => $failureCode,
            ];
            $this->audit->record('evidence.transfer_commit_failed', $actor, $evidence, $allianceId, $metadata);
            $this->outbox->record('evidence.transfer_commit_failed', $allianceId, $evidence, $metadata);
        });
    }
}
