<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationStatus;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferEvidenceDestinationSupport;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEvidenceDestinationReceipt;
use Illuminate\Support\Facades\DB;

final readonly class RecordTransferInvitationEvidence
{
    public function __construct(
        private TransferEvidenceDestinationSupport $support,
        private RecordTransferObservation $observations,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $planId,
        string $participantId,
        string $expectedWindowId,
        string $expectedTargetKingdomId,
        string $evidenceId,
        string $reviewId,
        string $schemaVersion,
        string $idempotencyKey,
        TransferInvitationStatus $status,
        string $observedAt,
        string $validUntil,
    ): TransferEvidenceDestinationReceipt {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $participantId, $expectedWindowId, $expectedTargetKingdomId, $evidenceId, $reviewId, $schemaVersion, $idempotencyKey, $status, $observedAt, $validUntil): TransferEvidenceDestinationReceipt {
            $context = $this->support->authorize($actorPlayerId, $allianceId);
            $existing = $this->support->existingReceipt($idempotencyKey);
            if ($existing instanceof TransferEvidenceDestinationReceipt) {
                return $existing;
            }
            $target = $this->support->lockScope($allianceId, $planId, $participantId, $expectedWindowId, $expectedTargetKingdomId, true);
            $observationId = $this->observations->handle(
                allianceId: $allianceId,
                actorPlayerId: $actorPlayerId,
                planId: $planId,
                participantId: $participantId,
                kind: TransferObservationKind::InvitationStatus,
                value: $status->value,
                sourceType: TransferSourceType::Evidence,
                sourceReference: 'Screenshot Intake review '.$reviewId,
                observedAt: $observedAt,
                validUntil: $validUntil,
                evidenceId: $evidenceId,
            );

            return $this->support->complete(
                $context,
                $target,
                $evidenceId,
                $reviewId,
                'transfer_invitation',
                $schemaVersion,
                $idempotencyKey,
                ['invitation_observation_id' => $observationId],
            );
        });
    }
}
