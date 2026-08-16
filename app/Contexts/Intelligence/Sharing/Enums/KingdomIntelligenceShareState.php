<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Sharing\Enums;

enum KingdomIntelligenceShareState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Declined = 'declined';
    case Revoked = 'revoked';
}
