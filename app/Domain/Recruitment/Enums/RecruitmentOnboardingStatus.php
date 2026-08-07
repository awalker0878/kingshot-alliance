<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Enums;

enum RecruitmentOnboardingStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Waived = 'waived';
}
