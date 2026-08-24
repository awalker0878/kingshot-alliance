<?php

declare(strict_types=1);

namespace App\ReadModels\EventManagement\Enums;

enum EventCommandState: string
{
    case Planning = 'planning';
    case NeedsAttention = 'needs_attention';
    case Ready = 'ready';
    case Active = 'active';
    case CloseoutRequired = 'closeout_required';
    case Complete = 'complete';
}
