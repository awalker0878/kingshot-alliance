<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Enums;

enum PlayerIdentitySource: string
{
    case Manual = 'manual';
    case GameApi = 'game_api';
    case Screenshot = 'screenshot';
    case Import = 'import';
    case IntelligenceObservation = 'intelligence_observation';
    case AllianceRoster = 'alliance_roster';
    case KingdomTransfer = 'kingdom_transfer';
    case AccountOnboarding = 'account_onboarding';
    case DataGovernance = 'data_governance';
    case TrustedDataset = 'trusted_dataset';
    case SystemReconciliation = 'system_reconciliation';
}
