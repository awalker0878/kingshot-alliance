<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum RallyAssignmentStatus: string
{
    case Assigned = 'assigned';
    case Standby = 'standby';
    case Participated = 'participated';
    case NoShow = 'no_show';
}
