<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\Models\Player;

final readonly class WithdrawTransferParticipant
{
    public function __construct(private TransitionTransferReadiness $readiness) {}

    public function handle(
        Alliance $alliance,
        Player $actor,
        string $planId,
        string $participantId,
    ): TransferParticipant {
        return $this->readiness->handle(
            $alliance,
            $actor,
            $planId,
            $participantId,
            TransferReadinessState::Withdrawn,
        );
    }
}
