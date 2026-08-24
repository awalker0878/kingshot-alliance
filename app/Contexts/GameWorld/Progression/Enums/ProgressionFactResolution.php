<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Enums;

enum ProgressionFactResolution: string
{
    case Known = 'known';
    case Unknown = 'unknown';
    case Conflicting = 'conflicting';
}
