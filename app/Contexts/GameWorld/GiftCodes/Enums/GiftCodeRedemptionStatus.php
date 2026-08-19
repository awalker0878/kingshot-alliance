<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeRedemptionStatus: string
{
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Redeemed = 'redeemed';
    case AlreadyRedeemed = 'already_redeemed';
    case InvalidCode = 'invalid_code';
    case Expired = 'expired';
    case WrongKingdom = 'wrong_kingdom';
    case RateLimited = 'rate_limited';
    case TransientFailure = 'transient_failure';
    case PermanentFailure = 'permanent_failure';

    public function retryable(): bool
    {
        return $this === self::RateLimited || $this === self::TransientFailure;
    }

    public function successful(): bool
    {
        return $this === self::Redeemed || $this === self::AlreadyRedeemed;
    }
}
