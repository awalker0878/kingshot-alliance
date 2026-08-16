<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Enums;

enum EventResponseSource: string
{
    case Self = 'self';
    case Coordinator = 'coordinator';
    case Import = 'import';
}
