<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferEvidenceDestinationSupport;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEvidenceDestinationReceipt;
use Illuminate\Support\Facades\DB;

final readonly class RecordTransferScorePassEvidence
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
        int $transferScore,
        int $passesAvailable,
        int $passesRequired,
        string $observedAt,
        string $validUntil,
    ): TransferEvidenceDestinationReceipt {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $participantId, $expectedWindowId, $expectedTargetKingdomId, $evidenceId, $reviewId, $schemaVersion, $idempotencyKey, $transferScore, $passesAvailable, $passesRequired, $observedAt, $validUntil): TransferEvidenceDestinationReceipt {
            $context = $this->support->authorize($actorPlayerId, $allianceId);
            $existing = $this->support->existingReceipt($idempotencyKey);
            if ($existing instanceof TransferEvidenceDestinationReceipt) {
                return $existing;
            }
            $target = $this->support->lockScope($allianceId, $planId, $participantId, $expectedWindowId, $expectedTargetKingdomId, true);
            $sourceReference = 'Screenshot Intake review '.$reviewId;
            $scoreId = $this->observations->handle(
                $allianceId,
                $actorPlayerId,
                $planId,
                $participantId,
                TransferObservationKind::TransferScore,
                $transferScore,
                TransferSourceType::Evidence,
                $sourceReference,
                $observedAt,
                $validUntil,
                evidenceId: $evidenceId,
            );
            $availableId = $this->observations->handle(
                $allianceId,
                $actorPlayerId,
                $planId,
                $participantId,
                TransferObservationKind::TransferPassesAvailable,
                $passesAvailable,
                TransferSourceType::Evidence,
                $sourceReference,
                $observedAt,
                $validUntil,
                evidenceId: $evidenceId,
            );
            $requiredId = $this->observations->handle(
                $allianceId,
                $actorPlayerId,
                $planId,
                $participantId,
                TransferObservationKind::TransferPassesRequired,
                $passesRequired,
                TransferSourceType::Evidence,
                $sourceReference,
                $observedAt,
                $validUntil,
                evidenceId: $evidenceId,
            );

            return $this->support->complete(
                $context,
                $target,
                $evidenceId,
                $reviewId,
                'transfer_score_passes',
                $schemaVersion,
                $idempotencyKey,
                [
                    'transfer_score_observation_id' => $scoreId,
                    'transfer_passes_available_observation_id' => $availableId,
                    'transfer_passes_required_observation_id' => $requiredId,
                ],
            );
        });
    }
}
