<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventAttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Excused = 'excused';
    case Unknown = 'unknown';
}
