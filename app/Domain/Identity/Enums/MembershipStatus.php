<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum MembershipStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';
    case Left = 'left';
    case Removed = 'removed';
}
