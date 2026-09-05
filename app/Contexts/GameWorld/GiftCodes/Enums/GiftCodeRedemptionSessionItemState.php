<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Enums;

enum GiftCodeRedemptionSessionItemState: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Completed = 'completed';
    case RetryWait = 'retry_wait';
    case Skipped = 'skipped';
    case Unavailable = 'unavailable';

    public function terminal(): bool
    {
        return in_array($this, [self::Completed, self::Skipped, self::Unavailable], true);
    }
}
