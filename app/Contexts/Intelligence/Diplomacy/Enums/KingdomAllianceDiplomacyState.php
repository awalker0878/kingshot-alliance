<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Diplomacy\Enums;

enum KingdomAllianceDiplomacyState: string
{
    case Unknown = 'unknown';
    case Neutral = 'neutral';
    case Friendly = 'friendly';
    case Nap = 'nap';
    case Ally = 'ally';
    case Rival = 'rival';
}
