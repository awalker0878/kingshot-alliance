<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Enums;

enum EventRecurrencePolicy: string
{
    case Disabled = 'disabled';
    case FixedInterval = 'fixed_interval';
    case Configurable = 'configurable';

    public function allowsRecurrence(): bool
    {
        return $this !== self::Disabled;
    }
}
