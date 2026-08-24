<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Enums;

enum ProgressionFactKind: string
{
    case HeroGeneration = 'hero_generation';
    case HeroTroopClass = 'hero_troop_class';
    case SystemMaxLevel = 'system_max_level';
    case GovernorGearRequirement = 'governor_gear_requirement';
    case TroopTierStats = 'troop_tier_stats';
    case AcademyResearchLevel = 'academy_research_level';
}
