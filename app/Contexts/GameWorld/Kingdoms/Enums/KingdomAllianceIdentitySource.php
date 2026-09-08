<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Enums;

enum KingdomAllianceIdentitySource: string
{
    case Manual = 'manual';
    case GameApi = 'game_api';
    case Screenshot = 'screenshot';
    case Import = 'import';
    case IntelligenceObservation = 'intelligence_observation';
    case TrustedDataset = 'trusted_dataset';
    case SystemReconciliation = 'system_reconciliation';
}
