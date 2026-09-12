<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Enums;

enum KingPerkReminderKind: string
{
    case Appointment24Hours = 'appointment_24_hours';
    case Appointment1Hour = 'appointment_1_hour';
    case Appointment10Minutes = 'appointment_10_minutes';
    case AppointmentUnconfirmed10Minutes = 'appointment_unconfirmed_10_minutes';
    case SkillSchedulingAvailable = 'skill_scheduling_available';
    case Skill1Hour = 'skill_1_hour';

    public function isAppointment(): bool
    {
        return match ($this) {
            self::SkillSchedulingAvailable, self::Skill1Hour => false,
            default => true,
        };
    }

    public function requiresManagerAuthority(): bool
    {
        return $this === self::AppointmentUnconfirmed10Minutes || ! $this->isAppointment();
    }

    /** @return list<string> */
    public function sourceStatuses(): array
    {
        return match ($this) {
            self::AppointmentUnconfirmed10Minutes => [KingPerkAppointmentStatus::Scheduled->value],
            self::SkillSchedulingAvailable => [KingSkillStatus::Planned->value],
            self::Skill1Hour => [KingSkillStatus::Planned->value, KingSkillStatus::ScheduledInGame->value],
            default => [KingPerkAppointmentStatus::Scheduled->value, KingPerkAppointmentStatus::Confirmed->value],
        };
    }

    public function leadMinutes(?KingSkill $skill = null): int
    {
        return match ($this) {
            self::Appointment24Hours => 1440,
            self::Appointment1Hour, self::Skill1Hour => 60,
            self::Appointment10Minutes, self::AppointmentUnconfirmed10Minutes => 10,
            self::SkillSchedulingAvailable => ($skill ?? throw new \LogicException('A scheduling reminder requires a King Skill.'))->advanceSchedulingMinutes(),
        };
    }
}
