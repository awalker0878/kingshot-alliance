<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordGovernorStatusEvidence;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordOfficialTransferGroupEvidence;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferInvitationEvidence;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferKingdomRulesEvidence;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferScorePassEvidence;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationStatus;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEvidenceDestinationReceipt;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\ReviewedTransferEvidenceCommitCommand;
use LogicException;
use Throwable;

final readonly class CommitReviewedTransferEvidence
{
    public function __construct(
        private BeginTransferEvidenceCommit $begin,
        private CompleteTransferEvidenceCommit $complete,
        private FailTransferEvidenceCommit $fail,
        private RecordGovernorStatusEvidence $governorStatus,
        private RecordTransferScorePassEvidence $scorePasses,
        private RecordTransferInvitationEvidence $invitation,
        private RecordTransferKingdomRulesEvidence $kingdomRules,
        private RecordOfficialTransferGroupEvidence $officialGroup,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $planId,
        string $participantId,
        string $reviewId,
    ): TransferEvidenceDestinationReceipt {
        $command = $this->begin->handle($actorPlayerId, $allianceId, $planId, $participantId, $reviewId);

        try {
            $receipt = $this->commit($actorPlayerId, $command);
            $this->complete->handle($actorPlayerId, $allianceId, $command->commitAttemptId, $receipt);

            return $receipt;
        } catch (Throwable $exception) {
            $this->fail->handle(
                $actorPlayerId,
                $allianceId,
                $command->commitAttemptId,
                substr(hash('sha256', $exception::class.':'.$exception->getMessage()), 0, 24),
            );
            throw $exception;
        }
    }

    private function commit(string $actorPlayerId, ReviewedTransferEvidenceCommitCommand $command): TransferEvidenceDestinationReceipt
    {
        return match ($command->kind) {
            EvidenceKind::TransferGovernorStatus => $this->governorStatus->handle(
                allianceId: $command->allianceId,
                actorPlayerId: $actorPlayerId,
                planId: $command->transferPlanId,
                participantId: $command->transferParticipantId,
                expectedWindowId: $command->transferWindowId,
                evidenceId: $command->evidenceId,
                reviewId: $command->reviewId,
                schemaVersion: $command->schemaVersion,
                idempotencyKey: $command->idempotencyKey,
                governorPower: $command->governorPower ?? throw new LogicException('Governor Power is missing from an approved Governor status review.'),
                observedAt: $command->observedAt,
                validUntil: $command->validUntil ?? throw new LogicException('Governor Power validity is missing from an approved review.'),
            ),
            EvidenceKind::TransferScorePasses => $this->scorePasses->handle(
                allianceId: $command->allianceId,
                actorPlayerId: $actorPlayerId,
                planId: $command->transferPlanId,
                participantId: $command->transferParticipantId,
                expectedWindowId: $command->transferWindowId,
                expectedTargetKingdomId: $command->targetKingdomId ?? throw new LogicException('Target Kingdom is missing from an approved score/pass review.'),
                evidenceId: $command->evidenceId,
                reviewId: $command->reviewId,
                schemaVersion: $command->schemaVersion,
                idempotencyKey: $command->idempotencyKey,
                transferScore: $command->transferScore ?? throw new LogicException('Transfer Score is missing from an approved review.'),
                passesAvailable: $command->transferPassesAvailable ?? throw new LogicException('Available Transfer Passes are missing from an approved review.'),
                passesRequired: $command->transferPassesRequired ?? throw new LogicException('Required Transfer Passes are missing from an approved review.'),
                observedAt: $command->observedAt,
                validUntil: $command->validUntil ?? throw new LogicException('Score/pass validity is missing from an approved review.'),
            ),
            EvidenceKind::TransferInvitation => $this->invitation->handle(
                allianceId: $command->allianceId,
                actorPlayerId: $actorPlayerId,
                planId: $command->transferPlanId,
                participantId: $command->transferParticipantId,
                expectedWindowId: $command->transferWindowId,
                expectedTargetKingdomId: $command->targetKingdomId ?? throw new LogicException('Target Kingdom is missing from an approved invitation review.'),
                evidenceId: $command->evidenceId,
                reviewId: $command->reviewId,
                schemaVersion: $command->schemaVersion,
                idempotencyKey: $command->idempotencyKey,
                status: TransferInvitationStatus::from($command->invitationStatus ?? throw new LogicException('Invitation status is missing from an approved review.')),
                observedAt: $command->observedAt,
                validUntil: $command->validUntil ?? throw new LogicException('Invitation validity is missing from an approved review.'),
            ),
            EvidenceKind::TransferTargetKingdomRules => $this->kingdomRules->handle(
                allianceId: $command->allianceId,
                actorPlayerId: $actorPlayerId,
                planId: $command->transferPlanId,
                participantId: $command->transferParticipantId,
                expectedWindowId: $command->transferWindowId,
                expectedTargetKingdomId: $command->targetKingdomId ?? throw new LogicException('Target Kingdom is missing from an approved target-rules review.'),
                evidenceId: $command->evidenceId,
                reviewId: $command->reviewId,
                schemaVersion: $command->schemaVersion,
                idempotencyKey: $command->idempotencyKey,
                powerCap: $command->targetPowerCap ?? throw new LogicException('Power Cap is missing from an approved target-rules review.'),
                classification: TransferKingdomClassification::from($command->kingdomClassification ?? 'unknown'),
                observedAt: $command->observedAt,
            ),
            EvidenceKind::TransferOfficialGroup => $this->officialGroup->handle(
                allianceId: $command->allianceId,
                actorPlayerId: $actorPlayerId,
                planId: $command->transferPlanId,
                participantId: $command->transferParticipantId,
                expectedWindowId: $command->transferWindowId,
                evidenceId: $command->evidenceId,
                reviewId: $command->reviewId,
                schemaVersion: $command->schemaVersion,
                idempotencyKey: $command->idempotencyKey,
                officialGroupIdentifier: $command->officialGroupIdentifier ?? throw new LogicException('Official Transfer Group identifier is missing from an approved review.'),
                kingdomNumbers: $command->officialGroupKingdomNumbers,
                observedAt: $command->observedAt,
            ),
            default => throw new LogicException('Unsupported Transfer Evidence destination schema.'),
        };
    }
}
