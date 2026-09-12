<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;

final class TransferPlanQuery
{
    public function currentForAlliance(string $allianceId): ?TransferPlan
    {
        foreach ([TransferPlanState::Open, TransferPlanState::Locked, TransferPlanState::Draft] as $state) {
            $plan = TransferPlan::query()->where('alliance_id', $allianceId)->where('state', $state->value)->with(['homeKingdom', 'window'])->orderByDesc('created_at')->orderByDesc('id')->first();
            if ($plan instanceof TransferPlan) {
                return $plan;
            }
        }

        return null;
    }

    public function mutableForAlliance(string $allianceId): ?TransferPlan
    {
        foreach ([TransferPlanState::Open, TransferPlanState::Draft] as $state) {
            $plan = TransferPlan::query()->where('alliance_id', $allianceId)->where('state', $state->value)->with(['homeKingdom', 'window'])->orderByDesc('created_at')->orderByDesc('id')->first();
            if ($plan instanceof TransferPlan) {
                return $plan;
            }
        }

        return null;
    }
}
