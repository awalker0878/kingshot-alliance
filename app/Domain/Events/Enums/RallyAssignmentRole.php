<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum RallyAssignmentRole: string
{
    case Lead = 'lead';
    case Joiner = 'joiner';
}
