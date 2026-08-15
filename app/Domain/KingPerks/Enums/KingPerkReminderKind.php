<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Enums;

enum KingPerkReminderKind: string
{
    case Appointment24Hours = 'appointment_24_hours';
    case Appointment1Hour = 'appointment_1_hour';
    case Appointment10Minutes = 'appointment_10_minutes';
    case AppointmentUnconfirmed10Minutes = 'appointment_unconfirmed_10_minutes';
    case SkillSchedulingAvailable = 'skill_scheduling_available';
    case Skill1Hour = 'skill_1_hour';
}
