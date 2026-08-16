<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;

final readonly class OpenTransferPlan
{
    public function __construct(private TransitionTransferPlan $transition) {}

    public function handle(Alliance $alliance, Player $actor, string $planId): TransferPlan
    {
        return $this->transition->handle(
            $alliance,
            $actor,
            $planId,
            TransferPlanState::Open,
            [TransferPlanState::Draft],
            'kingdoms.transfer_plan_opened',
        );
    }
}
