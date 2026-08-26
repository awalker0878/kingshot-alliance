<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferKingdomConditionWriter;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use Illuminate\Support\Facades\DB;

final readonly class RecordTransferKingdomCondition
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $authority,
        private TransferKingdomConditionWriter $writer,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $windowId,
        int|string $kingdomNumber,
        ?int $powerCap,
        TransferKingdomClassification $classification,
        TransferSourceType $sourceType,
        string $sourceReference,
        string $observedAt,
        bool $isCorrection = false,
        ?string $evidenceId = null,
    ): string {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $windowId, $kingdomNumber, $powerCap, $classification, $sourceType, $sourceReference, $observedAt, $isCorrection, $evidenceId): string {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);

            return $this->writer->append(
                $context,
                $allianceId,
                $windowId,
                $kingdomNumber,
                $powerCap,
                $classification,
                $sourceType,
                $sourceReference,
                $observedAt,
                $isCorrection,
                $evidenceId,
            );
        });
    }
}
