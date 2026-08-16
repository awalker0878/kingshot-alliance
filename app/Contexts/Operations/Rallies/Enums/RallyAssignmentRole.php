<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Enums;

enum RallyAssignmentRole: string
{
    case Lead = 'lead';
    case Joiner = 'joiner';
    case Standby = 'standby';
}
