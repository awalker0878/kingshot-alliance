<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum RecruitmentStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case InvitationOnly = 'invitation_only';
}
