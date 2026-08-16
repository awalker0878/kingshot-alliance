<?php

declare(strict_types=1);

namespace App\Workflows\KingdomTransfer\Queries;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Workflows\KingdomTransfer\Enums\TransferPlanState;
use App\Workflows\KingdomTransfer\Models\TransferPlan;
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

    public function mutableForAlliance(Alliance $alliance): ?TransferPlan
    {
        foreach ([TransferPlanState::Open, TransferPlanState::Draft] as $state) {
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
