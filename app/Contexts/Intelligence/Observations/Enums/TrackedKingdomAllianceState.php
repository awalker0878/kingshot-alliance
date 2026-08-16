<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Enums;

enum TrackedKingdomAllianceState: string
{
    case Active = 'active';
    case Archived = 'archived';
}
