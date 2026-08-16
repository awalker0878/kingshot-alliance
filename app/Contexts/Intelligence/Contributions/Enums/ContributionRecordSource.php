<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Enums;

enum ContributionRecordSource: string
{
    case Manual = 'manual';
    case SelfReported = 'self_reported';
}
