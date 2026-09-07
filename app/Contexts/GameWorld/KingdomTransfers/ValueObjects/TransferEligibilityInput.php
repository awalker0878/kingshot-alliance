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
        public TransferObservedValue $targetHeroGeneration,
        public TransferObservedValue $targetTruegoldLevel,
        public TransferObservedValue $targetCharacterAgeThresholdDays,
        public TransferObservedValue $targetCapacityRemaining,
        public TransferObservedValue $invitationCapacityRemaining,
        public TransferObservedValue $transferOpenCapacityRemaining,
        public TransferObservedValue $specialInvitesAvailable,
        public TransferObservedValue $governorPower,
        public TransferObservedValue $governorHeroGeneration,
        public TransferObservedValue $governorTruegoldLevel,
        public TransferObservedValue $characterAgeOverTargetDays,
        public TransferObservedValue $transferCooldownRemainingDays,
        public TransferObservedValue $targetExistingCharacterCount,
        public TransferObservedValue $invitationStatus,
        public TransferObservedValue $passesAvailable,
        public TransferObservedValue $passesRequired,
        public TransferObservedValue $resourceProtectionVerified,
        public TransferObservedValue $inGameRulesVerified,
    ) {}
}
