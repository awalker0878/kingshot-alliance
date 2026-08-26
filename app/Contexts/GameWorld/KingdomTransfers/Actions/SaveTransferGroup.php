<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferGroupWriter;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use Illuminate\Support\Facades\DB;

final readonly class SaveTransferGroup
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $authority,
        private TransferGroupWriter $writer,
    ) {}

    /** @param array{official_label:string,kingdom_numbers:list<int|string>,source_type:TransferSourceType,source_reference:string,observed_at:string,evidence_id?:string|null} $data */
    public function handle(string $allianceId, string $actorPlayerId, string $windowId, array $data): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $windowId, $data): string {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);

            return $this->writer->save(
                $context,
                $allianceId,
                $windowId,
                $data['official_label'],
                $data['kingdom_numbers'],
                $data['source_type'],
                $data['source_reference'],
                $data['observed_at'],
                $data['evidence_id'] ?? null,
            );
        });
    }
}
