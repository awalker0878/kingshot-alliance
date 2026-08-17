<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferGroupState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use Illuminate\Database\Eloquent\Collection;

final class TransferGroupQuery
{
    /** @return Collection<int, TransferGroup> */
    public function forPlan(string $allianceId, string $planId, bool $includeArchived = false): Collection
    {
        $query = TransferGroup::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_plan_id', $planId)
            ->with([
                'coordinator:id,current_name',
                'destinationKingdom:id,number',
            ]);

        if ($includeArchived === false) {
            $query->where('state', TransferGroupState::Active->value);
        }

        return $query
            ->orderByRaw("case state when 'active' then 0 else 1 end")
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }
}
