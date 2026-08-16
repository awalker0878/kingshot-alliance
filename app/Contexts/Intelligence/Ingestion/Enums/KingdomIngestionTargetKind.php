<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Enums;

enum KingdomIngestionTargetKind: string
{
    case PlayerSnapshot = 'player_snapshot';
    case AllianceObservation = 'alliance_observation';
}
