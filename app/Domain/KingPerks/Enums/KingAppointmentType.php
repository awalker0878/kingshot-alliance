<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Enums;

enum KingAppointmentType: string
{
    case NobleAdvisor = 'noble_advisor';
    case ChiefMinister = 'chief_minister';
    case FieldCommander = 'field_commander';
    case Marshal = 'marshal';
    case MinisterOfInterior = 'minister_of_interior';

    public function label(): string
    {
        return match ($this) {
            self::NobleAdvisor => 'Noble Advisor',
            self::ChiefMinister => 'Chief Minister',
            self::FieldCommander => 'Field Commander',
            self::Marshal => 'Marshal',
            self::MinisterOfInterior => 'Minister of Interior',
        };
    }

    /** Current KingShot appointment occupancy window. */
    public function durationMinutes(): int
    {
        return 30;
    }

    /** Current KingShot per-Player cooldown after an appointment. */
    public function playerCooldownMinutes(): int
    {
        return 60;
    }

    /** Current KingShot position lockout after a live appointment is cancelled. */
    public function cancelledPositionCooldownMinutes(): int
    {
        return 30;
    }

    public function recommendedFocus(): string
    {
        return match ($this) {
            self::NobleAdvisor => 'training',
            self::ChiefMinister => 'construction_research_training',
            self::FieldCommander, self::Marshal => 'combat',
            self::MinisterOfInterior => 'healing',
        };
    }
}
