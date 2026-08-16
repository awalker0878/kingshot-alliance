<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Enums;

enum MediaLifecycleStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
