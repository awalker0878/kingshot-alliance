<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Enums;

enum RecruitmentApplicationMode: string
{
    case Public = 'public';
    case Invitation = 'invitation';
    case Closed = 'closed';
}
