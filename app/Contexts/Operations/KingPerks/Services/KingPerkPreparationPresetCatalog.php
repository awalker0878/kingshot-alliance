<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Services;

use App\Contexts\Operations\KingPerks\Enums\KingAppointmentType;
use App\Contexts\Operations\KingPerks\Enums\KingSkill;
use Carbon\CarbonImmutable;

final class KingPerkPreparationPresetCatalog
{
    /** @return list<array<string, mixed>> */
    public function forWindow(CarbonImmutable $startsAt, CarbonImmutable $endsAt): array
    {
        $days = [];
        $cursor = $startsAt->utc();
        $day = 1;

        while ($cursor->lt($endsAt) && $day <= 7) {
            $segmentEnd = $cursor->addDay()->min($endsAt->utc());
            $days[] = [
                'day' => $day,
                'startsAt' => $cursor->toIso8601String(),
                'endsAt' => $segmentEnd->toIso8601String(),
                ...$this->recommendation($day),
            ];
            $cursor = $segmentEnd;
            $day++;
        }

        return $days;
    }

    /** @return array{focus:string|null,skill:string|null,appointmentTypes:list<string>,strategyNote:string} */
    private function recommendation(int $day): array
    {
        return match ($day) {
            1 => [
                'focus' => 'construction',
                'skill' => KingSkill::Groundworks->value,
                'appointmentTypes' => [KingAppointmentType::ChiefMinister->value],
                'strategyNote' => 'Strategy preset: stack the construction King Skill with Chief Minister rotations.',
            ],
            2 => [
                'focus' => 'research',
                'skill' => KingSkill::FreshIdeas->value,
                'appointmentTypes' => [KingAppointmentType::ChiefMinister->value],
                'strategyNote' => 'Strategy preset: stack the research King Skill with Chief Minister rotations.',
            ],
            3 => [
                'focus' => 'pet_training',
                'skill' => null,
                'appointmentTypes' => [],
                'strategyNote' => 'No King appointment recommendation is imposed for the pet-training focus.',
            ],
            4 => [
                'focus' => 'training',
                'skill' => KingSkill::Mobilize->value,
                'appointmentTypes' => [KingAppointmentType::NobleAdvisor->value, KingAppointmentType::ChiefMinister->value],
                'strategyNote' => 'Strategy preset: Noble Advisor first for training; Chief Minister is overflow capacity.',
            ],
            5 => [
                'focus' => 'all_out',
                'skill' => null,
                'appointmentTypes' => [],
                'strategyNote' => 'Keep rotations available for the Kingdom strategy rather than forcing a single focus.',
            ],
            6 => [
                'focus' => 'healing',
                'skill' => KingSkill::CommunityHealing->value,
                'appointmentTypes' => [KingAppointmentType::MinisterOfInterior->value],
                'strategyNote' => 'Strategy preset: prepare healing support approaching the battle transition.',
            ],
            default => [
                'focus' => null,
                'skill' => null,
                'appointmentTypes' => [],
                'strategyNote' => 'No default strategy preset.',
            ],
        };
    }
}
