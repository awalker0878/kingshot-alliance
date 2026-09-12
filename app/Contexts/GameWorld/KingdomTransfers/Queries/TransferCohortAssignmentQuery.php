<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCohortState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use Illuminate\Database\Eloquent\Builder;

/** Current assignment candidates. Callers supply an already-authorized participant. */
final class TransferCohortAssignmentQuery
{
    /** @return Builder<TransferCohort> */
    public function compatible(TransferParticipant $participant): Builder
    {
        $query = TransferCohort::query()->where('alliance_id', $participant->alliance_id)
            ->where('transfer_plan_id', $participant->transfer_plan_id)
            ->where('state', TransferCohortState::Active->value)
            ->where('direction', $participant->direction->value);
        if ($participant->withdrawn_at !== null || $participant->direction === TransferDirection::Staying) {
            $query->whereRaw('1 = 0');
        }
        if ($participant->direction === TransferDirection::Outgoing) {
            $query->where(fn (Builder $rows) => $rows->whereNull('destination_kingdom_id')
                ->orWhere('destination_kingdom_id', $participant->destination_kingdom_id));
        }

        return $query;
    }
}
