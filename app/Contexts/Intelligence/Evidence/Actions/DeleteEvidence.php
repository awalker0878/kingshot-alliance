<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Services\EvidenceRedactor;
use App\Contexts\Operations\Results\Queries\BearHuntEvidenceTargetQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteEvidence
{
    public function __construct(
        private BearHuntEvidenceTargetQuery $targets,
        private PlayerReferenceQuery $players,
        private EvidenceRedactor $redactor,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $occurrenceId, string $evidenceId): void
    {
        $target = $this->targets->authorizeManage($actorPlayerId, $occurrenceId);

        DB::transaction(function () use ($actorPlayerId, $evidenceId, $target): void {
            $this->targets->authorizeManage($actorPlayerId, $target->occurrenceId);
            $actor = $this->players->lockCurrent($actorPlayerId);
            $evidence = GameEvidence::query()
                ->whereKey($evidenceId)
                ->where('alliance_id', $target->allianceId)
                ->where('occurrence_id', $target->occurrenceId)
                ->lockForUpdate()
                ->firstOrFail();
            $status = EvidenceLifecycleStatus::from((string) $evidence->getRawOriginal('lifecycle_status'));
            if (in_array($status, [EvidenceLifecycleStatus::Classifying, EvidenceLifecycleStatus::Extracting, EvidenceLifecycleStatus::Committing], true)) {
                throw ValidationException::withMessages([
                    'evidence' => 'Evidence cannot be deleted while processing or committing is active.',
                ]);
            }

            $wasCommitted = EvidenceCommitAttempt::query()
                ->where('evidence_id', $evidence->id)
                ->where('status', 'succeeded')
                ->exists();

            $this->redactor->redact($evidence, 'user_requested');
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Deleted])->save();

            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'occurrence_id' => $target->occurrenceId,
                'committed_domain_state_preserved' => $wasCommitted,
            ];
            $this->audit->record('evidence.deleted', $actor, $evidence, $target->allianceId, $metadata);
            $this->outbox->record('evidence.deleted', $target->allianceId, $evidence, $metadata);
        });
    }
}
