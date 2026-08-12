<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Enums;

enum KingdomIntelligenceShareState: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Declined = 'declined';
    case Revoked = 'revoked';
}
