<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeEvidenceClassification: string
{
    case CommunityClaim = 'community_claim';
    case OfficialPublication = 'official_publication';
    case IndependentObservation = 'independent_observation';
    case ProviderOutcome = 'provider_outcome';
    case ModeratorDecision = 'moderator_decision';
}
