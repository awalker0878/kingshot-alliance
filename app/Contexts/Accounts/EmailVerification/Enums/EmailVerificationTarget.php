<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\EmailVerification\Enums;

enum EmailVerificationTarget: string
{
    case Account = 'account';
    case Pending = 'pending';
}
