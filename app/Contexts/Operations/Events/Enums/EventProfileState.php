<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Enums;

enum EventProfileState: string
{
    case Disabled = 'disabled';
    case Enabled = 'enabled';
}
