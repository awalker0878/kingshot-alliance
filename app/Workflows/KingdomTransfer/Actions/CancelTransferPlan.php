<?php

declare(strict_types=1);

namespace App\Workflows\KingdomTransfer\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Workflows\KingdomTransfer\Enums\TransferPlanState;
use App\Workflows\KingdomTransfer\Models\TransferPlan;

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
