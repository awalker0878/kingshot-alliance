<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Queries;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Models\TransferPlan;
use Illuminate\Database\Eloquent\Collection;

final class TransferPlanQuery
{
    /** @return Collection<int, TransferPlan> */
    public function forAlliance(Alliance $alliance): Collection
    {
        return TransferPlan::query()
            ->where('alliance_id', $alliance->id)
            ->with('homeKingdom')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    public function currentForAlliance(Alliance $alliance): ?TransferPlan
    {
        foreach ([TransferPlanState::Open, TransferPlanState::Locked, TransferPlanState::Draft] as $state) {
            $plan = TransferPlan::query()
                ->where('alliance_id', $alliance->id)
                ->where('state', $state->value)
                ->with('homeKingdom')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if ($plan instanceof TransferPlan) {
                return $plan;
            }
        }

        return null;
    }
}
