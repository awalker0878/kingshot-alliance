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
use Throwable;

final readonly class FailEvidenceCommit
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $attemptId, Throwable $exception): void
    {
        DB::transaction(function () use ($actorPlayerId, $attemptId, $exception): void {
            $attempt = EvidenceCommitAttempt::query()->whereKey($attemptId)->lockForUpdate()->first();
            if (! $attempt instanceof EvidenceCommitAttempt || $attempt->getRawOriginal('status') !== EvidenceCommitStatus::Pending->value) {
                return;
            }
            $evidence = GameEvidence::query()->whereKey($attempt->evidence_id)->lockForUpdate()->firstOrFail();
            $actor = $this->players->lockCurrent($actorPlayerId);
            $failureCode = substr(hash('sha256', $exception::class.':'.$exception->getMessage()), 0, 24);
            $attempt->forceFill([
                'status' => EvidenceCommitStatus::Failed,
                'failure_code' => $failureCode,
                'completed_at' => now(),
            ])->save();
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Approved])->save();
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'review_id' => (string) $attempt->review_id,
                'commit_attempt_id' => (string) $attempt->id,
                'failure_code' => $failureCode,
            ];
            $this->audit->record('evidence.commit_failed', $actor, $evidence, (string) $evidence->alliance_id, $metadata);
            $this->outbox->record('evidence.commit_failed', (string) $evidence->alliance_id, $evidence, $metadata);
        });
    }
}
