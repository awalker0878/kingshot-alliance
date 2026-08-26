<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferEvidenceDestinationSupport;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEvidenceDestinationReceipt;
use Illuminate\Support\Facades\DB;

final readonly class RecordTransferKingdomRulesEvidence
{
    public function __construct(
        private TransferEvidenceDestinationSupport $support,
        private KingdomReferenceQuery $kingdoms,
        private RecordTransferKingdomCondition $conditions,
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
        int $powerCap,
        TransferKingdomClassification $classification,
        string $observedAt,
    ): TransferEvidenceDestinationReceipt {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $participantId, $expectedWindowId, $expectedTargetKingdomId, $evidenceId, $reviewId, $schemaVersion, $idempotencyKey, $powerCap, $classification, $observedAt): TransferEvidenceDestinationReceipt {
            $context = $this->support->authorize($actorPlayerId, $allianceId);
            $existing = $this->support->existingReceipt($idempotencyKey);
            if ($existing instanceof TransferEvidenceDestinationReceipt) {
                return $existing;
            }
            $target = $this->support->lockScope($allianceId, $planId, $participantId, $expectedWindowId, $expectedTargetKingdomId, true);
            $kingdom = $this->kingdoms->require($expectedTargetKingdomId);
            $conditionId = $this->conditions->handle(
                allianceId: $allianceId,
                actorPlayerId: $actorPlayerId,
                windowId: $expectedWindowId,
                kingdomNumber: $kingdom->number,
                powerCap: $powerCap,
                classification: $classification,
                sourceType: TransferSourceType::Evidence,
                sourceReference: 'Screenshot Intake review '.$reviewId,
                observedAt: $observedAt,
                isCorrection: false,
                evidenceId: $evidenceId,
            );

            return $this->support->complete(
                $context,
                $target,
                $evidenceId,
                $reviewId,
                'transfer_target_kingdom_rules',
                $schemaVersion,
                $idempotencyKey,
                ['target_condition_observation_id' => $conditionId],
            );
        });
    }
}
