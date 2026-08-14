<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum EventResponseSource: string
{
    case Self = 'self';
    case Coordinator = 'coordinator';
    case Import = 'import';
}
