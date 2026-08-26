<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferObservationWriter;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use Illuminate\Support\Facades\DB;

final readonly class RecordTransferObservation
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $authority,
        private TransferObservationWriter $writer,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $planId,
        string $participantId,
        TransferObservationKind $kind,
        int|string|bool $value,
        TransferSourceType $sourceType,
        string $sourceReference,
        string $observedAt,
        ?string $validUntil,
        ?string $details = null,
        ?string $evidenceId = null,
    ): string {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $participantId, $kind, $value, $sourceType, $sourceReference, $observedAt, $validUntil, $details, $evidenceId): string {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);

            return $this->writer->append(
                $context,
                $allianceId,
                $planId,
                $participantId,
                $kind,
                $value,
                $sourceType,
                $sourceReference,
                $observedAt,
                $validUntil,
                $details,
                $evidenceId,
            );
        });
    }
}
