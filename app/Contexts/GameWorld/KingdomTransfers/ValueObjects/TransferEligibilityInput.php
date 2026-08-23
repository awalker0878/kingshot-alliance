<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\ValueObjects;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferWindowPhase;

final readonly class TransferEligibilityInput
{
    public function __construct(
        public TransferWindowPhase $phase,
        public TransferRequirementState $groupState,
        public ?string $sourceGroupLabel,
        public ?string $targetGroupLabel,
        public TransferObservedValue $targetPowerCap,
        public TransferKingdomClassification $targetClassification,
        public TransferObservedValue $governorPower,
        public TransferObservedValue $invitationStatus,
        public TransferObservedValue $passesAvailable,
        public TransferObservedValue $passesRequired,
        public TransferObservedValue $inGameRulesVerified,
    ) {}
}
