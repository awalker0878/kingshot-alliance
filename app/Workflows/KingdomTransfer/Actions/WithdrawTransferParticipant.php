<?php

declare(strict_types=1);

namespace App\Workflows\KingdomTransfer\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Workflows\KingdomTransfer\Enums\TransferReadinessState;
use App\Workflows\KingdomTransfer\Models\TransferParticipant;

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
