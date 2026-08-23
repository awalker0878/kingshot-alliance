<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Enums;

enum TransferEligibilityOutcome: string
{
    case EligibleNow = 'eligible_now';
    case EligibleWithAction = 'eligible_with_action';
    case Blocked = 'blocked';
    case NeedsVerification = 'needs_verification';
    case NotOpenYet = 'not_open_yet';
    case WindowClosed = 'window_closed';
    case NotApplicable = 'not_applicable';
}
