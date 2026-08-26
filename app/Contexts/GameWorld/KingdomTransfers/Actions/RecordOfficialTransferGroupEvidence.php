<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferEvidenceDestinationSupport;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferGroupWriter;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEvidenceDestinationReceipt;
use Illuminate\Support\Facades\DB;

final readonly class RecordOfficialTransferGroupEvidence
{
    public function __construct(
        private TransferEvidenceDestinationSupport $support,
        private TransferGroupWriter $groups,
    ) {}

    /** @param list<int> $kingdomNumbers */
    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $planId,
        string $participantId,
        string $expectedWindowId,
        string $evidenceId,
        string $reviewId,
        string $schemaVersion,
        string $idempotencyKey,
        string $officialGroupIdentifier,
        array $kingdomNumbers,
        string $observedAt,
    ): TransferEvidenceDestinationReceipt {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $participantId, $expectedWindowId, $evidenceId, $reviewId, $schemaVersion, $idempotencyKey, $officialGroupIdentifier, $kingdomNumbers, $observedAt): TransferEvidenceDestinationReceipt {
            $context = $this->support->authorize($actorPlayerId, $allianceId);
            $existing = $this->support->existingReceipt($idempotencyKey);
            if ($existing instanceof TransferEvidenceDestinationReceipt) {
                return $existing;
            }
            $target = $this->support->lockScope($allianceId, $planId, $participantId, $expectedWindowId, null, false);
            $groupId = $this->groups->save(
                $context,
                $allianceId,
                $expectedWindowId,
                $officialGroupIdentifier,
                $kingdomNumbers,
                TransferSourceType::Evidence,
                'Screenshot Intake review '.$reviewId,
                $observedAt,
                $evidenceId,
            );

            return $this->support->complete(
                $context,
                $target,
                $evidenceId,
                $reviewId,
                'transfer_official_group',
                $schemaVersion,
                $idempotencyKey,
                ['official_transfer_group_id' => $groupId],
            );
        });
    }
}
