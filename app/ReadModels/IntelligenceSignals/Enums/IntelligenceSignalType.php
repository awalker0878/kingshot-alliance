<?php

declare(strict_types=1);

namespace App\ReadModels\IntelligenceSignals\Enums;

enum IntelligenceSignalType: string
{
    case ObservationChange = 'observation_change';
    case StaleIntelligence = 'stale_intelligence';
    case TrackedEntityStateChanged = 'tracked_entity_state_changed';
    case TransferEvidenceExpiring = 'transfer_evidence_expiring';
    case ProgressionChanged = 'progression_changed';
    case PerformanceTrend = 'performance_trend';
    case RecruitmentChanged = 'recruitment_changed';
}
