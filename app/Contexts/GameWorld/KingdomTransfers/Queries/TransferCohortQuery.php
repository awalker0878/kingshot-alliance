<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCohortState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use Illuminate\Database\Eloquent\Collection;

final class TransferCohortQuery
{
    /** @return Collection<int,TransferCohort> */
    public function forPlan(string $allianceId, string $planId, bool $includeArchived = false): Collection
    {
        $query = TransferCohort::query()->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)->with(['coordinator:id,current_name', 'destinationKingdom:id,number']);
        if (! $includeArchived) {
            $query->where('state', TransferCohortState::Active->value);
        }

return $query->orderByRaw("case state when 'active' then 0 else 1 end")->orderBy('name')->orderBy('id')->get();
    }
}
