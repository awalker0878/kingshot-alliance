<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferObservationKind: string
{
    case GovernorPower = 'governor_power';
    case HeroGeneration = 'hero_generation';
    case TruegoldLevel = 'truegold_level';
    case CharacterAgeOverTargetDays = 'character_age_over_target_days';
    case TransferCooldownRemainingDays = 'transfer_cooldown_remaining_days';
    case TargetExistingCharacterCount = 'target_existing_character_count';
    case TransferScore = 'transfer_score';
    case TransferPassesAvailable = 'transfer_passes_available';
    case TransferPassesRequired = 'transfer_passes_required';
    case InvitationStatus = 'invitation_status';
    case ResourceProtectionVerified = 'resource_protection_verified';
    case InGameRulesVerified = 'in_game_rules_verified';

    public function usesNumericValue(): bool
    {
        return in_array($this, [
            self::GovernorPower,
            self::HeroGeneration,
            self::TruegoldLevel,
            self::CharacterAgeOverTargetDays,
            self::TransferCooldownRemainingDays,
            self::TargetExistingCharacterCount,
            self::TransferScore,
            self::TransferPassesAvailable,
            self::TransferPassesRequired,
        ], true);
    }

    public function usesBooleanValue(): bool
    {
        return in_array($this, [self::ResourceProtectionVerified, self::InGameRulesVerified], true);
    }

    public function requiresTargetKingdom(): bool
    {
        return in_array($this, [
            self::CharacterAgeOverTargetDays,
            self::TargetExistingCharacterCount,
            self::TransferPassesRequired,
            self::InvitationStatus,
            self::InGameRulesVerified,
        ], true);
    }
}
