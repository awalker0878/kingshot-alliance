<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class CompleteEvidenceCommit
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string,mixed> $receipt */
    public function handle(string $actorPlayerId, string $attemptId, string $destinationReportId, array $receipt): void
    {
        DB::transaction(function () use ($actorPlayerId, $attemptId, $destinationReportId, $receipt): void {
            $attempt = EvidenceCommitAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
            if ($attempt->getRawOriginal('status') === EvidenceCommitStatus::Succeeded->value) {
                return;
            }
            $evidence = GameEvidence::query()->whereKey($attempt->evidence_id)->lockForUpdate()->firstOrFail();
            $actor = $this->players->lockCurrent($actorPlayerId);
            $attempt->forceFill([
                'status' => EvidenceCommitStatus::Succeeded,
                'destination_report_id' => $destinationReportId,
                'destination_receipt' => $receipt,
                'failure_code' => null,
                'completed_at' => now(),
            ])->save();
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Committed])->save();
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'review_id' => (string) $attempt->review_id,
                'commit_attempt_id' => (string) $attempt->id,
                'destination_report_id' => $destinationReportId,
            ];
            $this->audit->record('evidence.commit_succeeded', $actor, $evidence, (string) $evidence->alliance_id, $metadata);
            $this->outbox->record('evidence.commit_succeeded', (string) $evidence->alliance_id, $evidence, $metadata);
        });
    }
}
