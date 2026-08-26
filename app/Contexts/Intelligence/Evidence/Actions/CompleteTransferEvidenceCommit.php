<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEvidenceTargetQuery;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEvidenceDestinationReceipt;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceCommitAttempt;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class CompleteTransferEvidenceCommit
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
        TransferEvidenceDestinationReceipt $receipt,
    ): void {
        $this->targets->authorizeAllianceManage($actorPlayerId, $allianceId);

        DB::transaction(function () use ($actorPlayerId, $allianceId, $commitAttemptId, $receipt): void {
            $this->targets->authorizeAllianceManage($actorPlayerId, $allianceId);
            $actor = $this->players->lockCurrent($actorPlayerId);
            $attempt = TransferEvidenceCommitAttempt::query()
                ->whereKey($commitAttemptId)
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($attempt->status === EvidenceCommitStatus::Succeeded) {
                return;
            }
            $attempt->forceFill([
                'status' => EvidenceCommitStatus::Succeeded,
                'destination_receipt_id' => $receipt->receiptId,
                'destination_receipt' => [
                    'receipt_id' => $receipt->receiptId,
                    'destination_ids' => $receipt->destinationIds,
                    'idempotent_replay' => $receipt->idempotentReplay,
                ],
                'completed_at' => now(),
                'failure_code' => null,
            ])->save();
            $evidence = GameEvidence::query()->whereKey($attempt->evidence_id)->lockForUpdate()->firstOrFail();
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Committed])->save();
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'review_id' => (string) $attempt->transfer_review_id,
                'commit_attempt_id' => (string) $attempt->id,
                'destination_receipt_id' => $receipt->receiptId,
                'destination_count' => count($receipt->destinationIds),
                'idempotent_replay' => $receipt->idempotentReplay,
            ];
            $this->audit->record('evidence.transfer_commit_succeeded', $actor, $evidence, $allianceId, $metadata);
            $this->outbox->record('evidence.transfer_commit_succeeded', $allianceId, $evidence, $metadata);
        });
    }
}
