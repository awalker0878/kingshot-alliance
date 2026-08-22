<?php

declare(strict_types=1);

namespace App\Workflows\ScreenshotIntake;

use App\Contexts\Intelligence\Evidence\Actions\BeginEvidenceCommit;
use App\Contexts\Intelligence\Evidence\Actions\CompleteEvidenceCommit;
use App\Contexts\Intelligence\Evidence\Actions\FailEvidenceCommit;
use App\Contexts\Operations\Results\Actions\RecordBearHuntBattleReport;
use App\Contexts\Operations\Results\ValueObjects\BearHuntBattleReportReceipt;
use Throwable;

final readonly class CommitBearHuntEvidence
{
    public function __construct(
        private BeginEvidenceCommit $begin,
        private RecordBearHuntBattleReport $record,
        private CompleteEvidenceCommit $complete,
        private FailEvidenceCommit $fail,
    ) {}

    public function handle(string $actorPlayerId, string $reviewId): BearHuntBattleReportReceipt
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
            $this->complete->handle($actorPlayerId, $command->commitAttemptId, $receipt->reportId, $receipt->toArray());

            return $receipt;
        } catch (Throwable $exception) {
            $this->fail->handle($actorPlayerId, $command->commitAttemptId, $exception);
            throw $exception;
        }
    }
}
