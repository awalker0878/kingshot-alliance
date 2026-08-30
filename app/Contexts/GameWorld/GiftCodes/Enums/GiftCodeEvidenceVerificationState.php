<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeEvidenceVerificationState: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Quarantined = 'quarantined';

    public function accepted(): bool
    {
        return $this === self::Verified;
    }
}
