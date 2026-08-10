<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Enums;

enum KingdomAllianceDiplomacyState: string
{
    case Unknown = 'unknown';
    case Neutral = 'neutral';
    case Friendly = 'friendly';
    case Nap = 'nap';
    case Ally = 'ally';
    case Rival = 'rival';
}
