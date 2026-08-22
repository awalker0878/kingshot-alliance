<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\Intelligence\Evidence\ValueObjects\EvidenceCommitReceipt;
use App\Contexts\Operations\Results\Actions\RecordBearHuntBattleReport;
use Throwable;

final readonly class CommitReviewedBearHuntEvidence
{
    public function __construct(
        private BeginEvidenceCommit $begin,
        private RecordBearHuntBattleReport $record,
        private CompleteEvidenceCommit $complete,
        private FailEvidenceCommit $fail,
    ) {}

    public function handle(string $actorPlayerId, string $reviewId): EvidenceCommitReceipt
    {
        $command = $this->begin->handle($actorPlayerId, $reviewId);

        try {
            $receipt = $this->record->handle(
                actorPlayerId: $actorPlayerId,
                occurrenceId: $command->occurrenceId,
                sourceEvidenceId: $command->evidenceId,
                sourceCommitAttemptId: $command->commitAttemptId,
                idempotencyKey: $command->idempotencyKey,
                reportFingerprint: $command->reportFingerprint,
                reportTimestampText: $command->reportTimestampText,
                entries: $command->entries,
            );
            $this->complete->handle(
                $actorPlayerId,
                $command->commitAttemptId,
                $receipt->reportId,
                $receipt->toArray(),
            );

            return new EvidenceCommitReceipt(
                reportId: $receipt->reportId,
                entryCount: $receipt->entryCount,
                idempotentReplay: $receipt->idempotentReplay,
            );
        } catch (Throwable $exception) {
            $this->fail->handle($actorPlayerId, $command->commitAttemptId, $exception);
            throw $exception;
        }
    }
}
