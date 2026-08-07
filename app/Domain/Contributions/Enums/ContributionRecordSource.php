<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Enums;

enum ContributionRecordSource: string
{
    case Manual = 'manual';
    case SelfReported = 'self_reported';
    case EventParticipation = 'event_participation';
}
