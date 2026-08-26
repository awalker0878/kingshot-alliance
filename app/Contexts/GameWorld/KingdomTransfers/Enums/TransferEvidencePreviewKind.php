<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferEvidencePreviewKind: string
{
    case GovernorStatus = 'transfer_governor_status';
    case ScorePasses = 'transfer_score_passes';
    case Invitation = 'transfer_invitation';
    case TargetKingdomRules = 'transfer_target_kingdom_rules';
    case OfficialGroup = 'transfer_official_group';
}
