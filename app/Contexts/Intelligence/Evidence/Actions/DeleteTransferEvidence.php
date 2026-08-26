<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEvidenceTargetQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Services\EvidenceRedactor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteTransferEvidence
{
    public function __construct(
        private TransferEvidenceTargetQuery $targets,
        private PlayerReferenceQuery $players,
        private EvidenceRedactor $redactor,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $planId,
        string $participantId,
        string $evidenceId,
    ): void {
        $this->targets->authorizeManage($actorPlayerId, $allianceId, $planId, $participantId);

        DB::transaction(function () use ($actorPlayerId, $allianceId, $planId, $participantId, $evidenceId): void {
            $this->targets->authorizeManage($actorPlayerId, $allianceId, $planId, $participantId);
            $actor = $this->players->lockCurrent($actorPlayerId);
            $evidence = GameEvidence::query()
                ->whereKey($evidenceId)
                ->where('alliance_id', $allianceId)
                ->whereNull('occurrence_id')
                ->where('transfer_plan_id', $planId)
                ->where('transfer_participant_id', $participantId)
                ->lockForUpdate()
                ->firstOrFail();
            $status = $evidence->lifecycle_status;
            if (in_array($status, [EvidenceLifecycleStatus::Classifying, EvidenceLifecycleStatus::Extracting, EvidenceLifecycleStatus::Committing], true)) {
                throw ValidationException::withMessages([
                    'evidence' => 'Transfer Evidence cannot be deleted while processing or committing is active.',
                ]);
            }

            $wasCommitted = TransferEvidenceCommitAttempt::query()
                ->where('evidence_id', $evidence->id)
                ->where('status', EvidenceCommitStatus::Succeeded->value)
                ->exists();

            $this->redactor->redact($evidence, 'user_requested');
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Deleted])->save();
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'evidence_kind' => $evidence->expected_kind->value,
                'scope' => 'transfer_participant',
                'committed_transfer_state_preserved' => $wasCommitted,
            ];
            $this->audit->record('evidence.transfer_deleted', $actor, $evidence, $allianceId, $metadata);
            $this->outbox->record('evidence.transfer_deleted', $allianceId, $evidence, $metadata);
        });
    }
}
