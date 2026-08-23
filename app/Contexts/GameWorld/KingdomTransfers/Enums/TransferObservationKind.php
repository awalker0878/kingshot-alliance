<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferObservationKind: string
{
    case GovernorPower = 'governor_power';
    case TransferScore = 'transfer_score';
    case TransferPassesAvailable = 'transfer_passes_available';
    case TransferPassesRequired = 'transfer_passes_required';
    case InvitationStatus = 'invitation_status';
    case InGameRulesVerified = 'in_game_rules_verified';

    public function usesNumericValue(): bool
    {
        return in_array($this, [self::GovernorPower, self::TransferScore, self::TransferPassesAvailable, self::TransferPassesRequired], true);
    }

    public function requiresTargetKingdom(): bool
    {
        return in_array($this, [self::TransferPassesRequired, self::InvitationStatus, self::InGameRulesVerified], true);
    }
}
