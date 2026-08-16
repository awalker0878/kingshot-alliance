<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Enums;

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

    /** Current observed appointment occupancy. Kept in the catalogue so game changes remain data-rule changes. */
    public function durationMinutes(): int
    {
        return 30;
    }

    /** Current documented per-Player appointment cooldown. */
    public function playerCooldownMinutes(): int
    {
        return 60;
    }

    /**
     * The public help text confirms the cooldown duration but is not explicit about its anchor.
     * We use the conservative appointment-end anchor and expose it to the UI/documentation so it
     * can be changed in one catalogue location after in-game verification.
     */
    public function playerCooldownAnchor(): string
    {
        return 'appointment_end';
    }

    /** Current documented position lockout after a live appointment is cancelled. */
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
