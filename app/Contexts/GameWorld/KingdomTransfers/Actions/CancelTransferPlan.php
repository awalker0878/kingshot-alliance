<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;

final readonly class CancelTransferPlan
{
    public function __construct(private TransitionTransferPlan $transition) {}

    public function handle(string $allianceId, string $actorPlayerId, string $planId): void
    {
        $this->transition->handle(
            $allianceId,
            $actorPlayerId,
            $planId,
            TransferPlanState::Cancelled,
            [TransferPlanState::Draft, TransferPlanState::Open, TransferPlanState::Locked],
            'kingdoms.transfer_plan_cancelled',
        );
    }
}
