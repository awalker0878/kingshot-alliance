<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Models\TransferPlan;

final readonly class CancelTransferPlan
{
    public function __construct(private TransitionTransferPlan $transition) {}

    public function handle(Alliance $alliance, Player $actor, string $planId): TransferPlan
    {
        return $this->transition->handle(
            $alliance,
            $actor,
            $planId,
            TransferPlanState::Cancelled,
            [TransferPlanState::Draft, TransferPlanState::Open, TransferPlanState::Locked],
            'kingdoms.transfer_plan_cancelled',
        );
    }
}
