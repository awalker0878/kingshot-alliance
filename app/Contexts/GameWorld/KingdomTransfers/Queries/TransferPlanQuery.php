<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
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
