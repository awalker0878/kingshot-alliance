<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Enums;

enum EvidenceKind: string
{
    case Unknown = 'unknown';
    case BearHuntBattleReport = 'bear_hunt_battle_report';
    case TransferGovernorStatus = 'transfer_governor_status';
    case TransferScorePasses = 'transfer_score_passes';
    case TransferInvitation = 'transfer_invitation';
    case TransferTargetKingdomRules = 'transfer_target_kingdom_rules';
    case TransferOfficialGroup = 'transfer_official_group';

    public function isTransfer(): bool
    {
        return in_array($this, [
            self::TransferGovernorStatus,
            self::TransferScorePasses,
            self::TransferInvitation,
            self::TransferTargetKingdomRules,
            self::TransferOfficialGroup,
        ], true);
    }

    /** @return list<self> */
    public static function transferCases(): array
    {
        return [
            self::TransferGovernorStatus,
            self::TransferScorePasses,
            self::TransferInvitation,
            self::TransferTargetKingdomRules,
            self::TransferOfficialGroup,
        ];
    }
}
