<?php

declare(strict_types=1);

namespace App\ReadModels\EventManagement\Enums;

enum EventCommandSeverity: string
{
    case Blocking = 'blocking';
    case Warning = 'warning';
    case Informational = 'informational';
}
