<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomMaps\Enums;

enum MapDatasetConfidence: string
{
    case Official = 'official';
    case VerifiedObservation = 'verified_observation';
    case CommunityObserved = 'community_observed';
}
