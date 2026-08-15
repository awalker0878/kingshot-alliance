<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Enums;

enum KingPerkPushCategory: string
{
    case Construction = 'construction';
    case Research = 'research';
    case Training = 'training';
    case Healing = 'healing';
    case Combat = 'combat';

    public function label(): string
    {
        return match ($this) {
            self::Construction => 'Construction',
            self::Research => 'Research',
            self::Training => 'Troop Training',
            self::Healing => 'Healing',
            self::Combat => 'Combat',
        };
    }

    /** @return list<KingAppointmentType> */
    public function preferredAppointments(): array
    {
        return match ($this) {
            self::Construction, self::Research => [KingAppointmentType::ChiefMinister],
            self::Training => [KingAppointmentType::NobleAdvisor, KingAppointmentType::ChiefMinister],
            self::Healing => [KingAppointmentType::MinisterOfInterior],
            self::Combat => [KingAppointmentType::FieldCommander, KingAppointmentType::Marshal],
        };
    }
}
