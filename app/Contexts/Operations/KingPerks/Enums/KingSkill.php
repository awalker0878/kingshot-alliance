<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Enums;

enum KingSkill: string
{
    case Groundworks = 'groundworks';
    case FreshIdeas = 'fresh_ideas';
    case Mobilize = 'mobilize';
    case CommunityHealing = 'community_healing';

    public function label(): string
    {
        return match ($this) {
            self::Groundworks => 'Groundworks',
            self::FreshIdeas => 'Fresh Ideas',
            self::Mobilize => 'Mobilize',
            self::CommunityHealing => 'Community Healing',
        };
    }

    public function recommendedFocus(): string
    {
        return match ($this) {
            self::Groundworks => 'construction',
            self::FreshIdeas => 'research',
            self::Mobilize => 'training',
            self::CommunityHealing => 'healing',
        };
    }

    public function advanceSchedulingMinutes(): int
    {
        return 2880;
    }
}
