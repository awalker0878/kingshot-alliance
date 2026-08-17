<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;

final readonly class LockTransferPlan
{
    public function __construct(private TransitionTransferPlan $transition) {}

    public function handle(string $allianceId, string $actorPlayerId, string $planId): void
    {
        $this->transition->handle(
            $allianceId,
            $actorPlayerId,
            $planId,
            TransferPlanState::Locked,
            [TransferPlanState::Open],
            'kingdoms.transfer_plan_locked',
        );
    }
}
