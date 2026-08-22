<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Jobs\ClassifyGameEvidenceJob;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Results\Queries\BearHuntEvidenceTargetQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RetryEvidenceProcessing
{
    public function __construct(
        private BearHuntEvidenceTargetQuery $targets,
        private PlayerReferenceQuery $players,
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
            if ($status !== EvidenceLifecycleStatus::Failed) {
                throw ValidationException::withMessages([
                    'evidence' => 'Only failed evidence processing can be retried.',
                ]);
            }
            if ($evidence->path === null) {
                throw ValidationException::withMessages([
                    'evidence' => 'Deleted evidence cannot be processed again.',
                ]);
            }

            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Uploaded])->save();
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'occurrence_id' => $target->occurrenceId,
            ];
            $this->audit->record('evidence.retry_requested', $actor, $evidence, $target->allianceId, $metadata);
            $this->outbox->record('evidence.retry_requested', $target->allianceId, $evidence, $metadata);
        });

        ClassifyGameEvidenceJob::dispatch($evidenceId);
    }
}
