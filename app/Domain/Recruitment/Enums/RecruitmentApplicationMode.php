<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Enums;

use App\Domain\Memberships\Models\Invitation;

enum RecruitmentApplicationMode: string
{
    case Public = 'public';
    case Invitation = 'invitation';
    case Closed = 'closed';
}
