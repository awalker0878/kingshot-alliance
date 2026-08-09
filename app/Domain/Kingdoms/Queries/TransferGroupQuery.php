<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Queries;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Models\TransferGroup;
use App\Domain\Kingdoms\Models\TransferPlan;
use Illuminate\Database\Eloquent\Collection;

final class TransferGroupQuery
{
    /** @return Collection<int, TransferGroup> */
    public function forPlan(Alliance $alliance, TransferPlan $plan, bool $includeArchived = false): Collection
    {
        $query = TransferGroup::query()
            ->where('alliance_id', $alliance->id)
            ->where('transfer_plan_id', $plan->id)
            ->with('coordinators.user:id,name,email');

        if ($includeArchived === false) {
            $query->whereNull('archived_at');
        }

        return $query
            ->orderByRaw('case when archived_at is null then 0 else 1 end')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }
}
