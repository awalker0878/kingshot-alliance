<?php

declare(strict_types=1);

namespace App\ReadModels\TransferManagement\Enums;

enum TransferChoiceKind: string
{
    case Windows = 'windows';
    case Coordinators = 'coordinators';
    case Roster = 'roster';
}
