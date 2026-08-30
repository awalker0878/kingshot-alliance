<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeModerationAction: string
{
    case Verify = 'verify';
    case Reject = 'reject';
    case Quarantine = 'quarantine';
    case Restore = 'restore';
    case CorrectExpiry = 'correct_expiry';
    case ResolveDispute = 'resolve_dispute';

    public function requiresReason(): bool
    {
        return in_array($this, [
            self::Reject,
            self::Quarantine,
            self::CorrectExpiry,
            self::ResolveDispute,
        ], true);
    }
}
