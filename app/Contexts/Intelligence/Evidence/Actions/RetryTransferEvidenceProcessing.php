<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEvidenceTargetQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Jobs\ClassifyGameEvidenceJob;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RetryTransferEvidenceProcessing
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
            if ($evidence->lifecycle_status !== EvidenceLifecycleStatus::Failed) {
                throw ValidationException::withMessages([
                    'evidence' => 'Only failed Transfer Evidence processing can be retried.',
                ]);
            }
            if ($evidence->path === null) {
                throw ValidationException::withMessages([
                    'evidence' => 'Deleted or redacted Evidence cannot be processed again.',
                ]);
            }

            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Uploaded])->save();
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'evidence_kind' => $evidence->expected_kind->value,
                'scope' => 'transfer_participant',
            ];
            $this->audit->record('evidence.transfer_retry_requested', $actor, $evidence, $allianceId, $metadata);
            $this->outbox->record('evidence.transfer_retry_requested', $allianceId, $evidence, $metadata);
        });

        ClassifyGameEvidenceJob::dispatch($evidenceId);
    }
}
