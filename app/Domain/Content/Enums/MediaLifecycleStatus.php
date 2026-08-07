<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum MediaLifecycleStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
