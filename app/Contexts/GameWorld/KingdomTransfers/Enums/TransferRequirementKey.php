<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferRequirementKey: string
{
    case WindowPhase = 'window_phase';
    case TransferGroup = 'transfer_group';
    case HeroGeneration = 'hero_generation';
    case TruegoldLevel = 'truegold_level';
    case CharacterAge = 'character_age';
    case TransferCooldown = 'transfer_cooldown';
    case TargetCharacterLimit = 'target_character_limit';
    case PowerCap = 'power_cap';
    case Invitation = 'invitation';
    case TargetCapacity = 'target_capacity';
    case InvitationCapacity = 'invitation_capacity';
    case TransferOpenCapacity = 'transfer_open_capacity';
    case TransferPasses = 'transfer_passes';
    case ResourceProtection = 'resource_protection';
    case InGameRules = 'in_game_rules';
}
