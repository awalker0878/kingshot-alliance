<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Enums;

enum KingdomIngestionTargetKind: string
{
    case PlayerSnapshot = 'player_snapshot';
    case AllianceObservation = 'alliance_observation';
}
