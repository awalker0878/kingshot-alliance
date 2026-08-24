<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Enums;

enum EvidenceClassification: string
{
    case OperationalFact = 'operational_fact';
    case GameFact = 'game_fact';
    case AllianceStrategy = 'alliance_strategy';
    case Observation = 'observation';
}
