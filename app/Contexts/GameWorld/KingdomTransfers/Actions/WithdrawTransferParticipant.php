<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;

final readonly class WithdrawTransferParticipant
{
    public function __construct(private TransitionTransferReadiness $readiness) {}

    public function handle(string $allianceId, string $actorPlayerId, string $planId, string $participantId): void
    {
        $this->readiness->handle(
            $allianceId,
            $actorPlayerId,
            $planId,
            $participantId,
            TransferReadinessState::Withdrawn,
        );
    }
}
