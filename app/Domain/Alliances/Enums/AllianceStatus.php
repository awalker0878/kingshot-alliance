<?php

declare(strict_types=1);

namespace App\Domain\Alliances\Enums;

enum AllianceStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
    case Deleted = 'deleted';
}
