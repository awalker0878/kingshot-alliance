<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\TransferReadinessState;
use App\Domain\Kingdoms\Models\TransferParticipant;

final readonly class WithdrawTransferParticipant
{
    public function __construct(private TransitionTransferReadiness $readiness) {}

    public function handle(
        Alliance $alliance,
        User $actor,
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
