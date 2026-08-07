<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum AllianceStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
