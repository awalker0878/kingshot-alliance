<?php

declare(strict_types=1);

namespace App\ReadModels\EventManagement\Enums;

enum EventCommandItemStatus: string
{
    case Complete = 'complete';
    case NeedsAttention = 'needs_attention';
    case Warning = 'warning';
    case Unknown = 'unknown';
    case NotApplicable = 'not_applicable';
}
